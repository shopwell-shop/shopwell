<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Service;

use Payum\Core\GatewayFactoryInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentTransactionCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentTransactionEntity;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PayResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentOrderStates;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Service\PaymentCaptureService;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Service\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Shopwell\Core\System\Payment\Struct\PayParams;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCaptureService::class)]
class PaymentCaptureServiceTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    private const CREATED_STATE = 'state-created';

    private const PAY_RESULT = 'pay-result';

    public function testCaptureThrowsWhenTheOrderIsNotPayable(): void
    {
        $order = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]);

        $service = $this->service(static::createStub(EntityRepository::class), $this->gateway());

        $this->expectExceptionObject(PaymentException::orderNotPayable('PG0001'));

        $service->capture($this->app(), $order);
    }

    public function testCaptureOpensTheOrderAndReturnsThePayResult(): void
    {
        $transactionRepository = static::createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(PaymentTransactionEntity::TYPE_CAPTURE, $data[0]['type']);
                static::assertSame(PaymentTransactionEntity::STATUS_PENDING, $data[0]['status']);
                static::assertSame('alipay', $data[0]['providerTechnicalName']);
                static::assertSame('alipay_h5', $data[0]['channelTechnicalName']);
                static::assertStringStartsWith('PT20240101000000', $data[0]['transactionNo']);

                return true;
            }));
        $transactionRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(PaymentTransactionEntity::STATUS_SUCCESS, $data[0]['status']);
                static::assertSame('channel-trade-1', $data[0]['outTransactionId']);
                static::assertNull($data[0]['errorCode']);
                static::assertNull($data[0]['errorMessage']);

                return true;
            }));

        $payResult = new PayResult(
            new PayParams(PaymentFlow::REDIRECT, 'https://pay.example.test', 'GET', null, ['marker' => self::PAY_RESULT]),
            'channel-trade-1'
        );
        $gateway = $this->gateway($payResult);

        $service = $this->service($transactionRepository, $gateway);

        $result = $service->capture($this->app(), $this->order());

        static::assertSame($payResult, $result);
        static::assertSame('channel-trade-1', $result->outPaymentNo);
        static::assertSame(self::PAY_RESULT, $result->params->extra['marker']);
    }

    public function testCaptureMarksTheOrderOpenedAfterASuccessfulCapture(): void
    {
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_PROCESS));

        $service = $this->service(
            static::createStub(EntityRepository::class),
            $this->gateway(new PayResult(new PayParams(PaymentFlow::REDIRECT), 'channel-trade-1')),
            $this->orderService($stateMachine)
        );

        $service->capture($this->app(), $this->order());
    }

    public function testCaptureRecordsTheFailureAndThrowsAGatewayError(): void
    {
        $transactionRepository = static::createMock(EntityRepository::class);
        $transactionRepository->expects($this->once())->method('create');
        $transactionRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(PaymentTransactionEntity::STATUS_FAILED, $data[0]['status']);
                static::assertSame('boom', $data[0]['errorMessage']);
                static::assertSame('42', $data[0]['errorCode']);

                return true;
            }));

        $service = $this->service($transactionRepository, $this->failingGateway());

        $this->expectExceptionObject(PaymentException::gatewayError('alipay_h5', 'boom'));

        $service->capture($this->app(), $this->order());
    }

    /**
     * @param EntityRepository<PaymentTransactionCollection> $transactionRepository
     */
    private function service(
        EntityRepository $transactionRepository,
        GatewayInterface $gateway,
        ?PaymentOrderService $orderService = null
    ): PaymentCaptureService {
        return new PaymentCaptureService(
            $transactionRepository,
            new PaymentGatewayFactory(),
            $this->configResolver($gateway),
            $orderService ?? $this->orderService(),
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class), 'https://base.example.test'),
            new MockClock(self::NOW)
        );
    }

    private function gateway(?PayResult $payResult = null): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($payResult): void {
            static::assertInstanceOf(Capture::class, $request);

            $model = $request->getModel();
            static::assertInstanceOf(\ArrayObject::class, $model);

            $model->offsetSet(PaymentGatewayModel::REQUEST, ['out_trade_no' => 'app-trade-1']);
            $model->offsetSet(PaymentGatewayModel::RESPONSE, ['code' => 'ok']);

            if ($payResult instanceof PayResult) {
                $model->offsetSet(PaymentGatewayModel::RESULT, $payResult);
            }
        });

        return $gateway;
    }

    private function failingGateway(): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(new \RuntimeException('boom', 42));

        return $gateway;
    }

    /**
     * The gateway factory of the resolved provider hands out the very gateway the test drives.
     */
    private function configResolver(GatewayInterface $gateway): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $gatewayFactory = static::createStub(GatewayFactoryInterface::class);
        $gatewayFactory->method('create')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = 'app-id';
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
        $appProvider->mode = PaymentAppProviderEntity::MODE_OWN;
        $appProvider->credentials = $cipher->encrypt(['app_id' => 'own-app-id']);

        $appChannel = new PaymentAppChannelEntity();
        $appChannel->setUniqueIdentifier('app-channel-id');
        $appChannel->paymentAppId = 'app-id';
        $appChannel->channelTechnicalName = 'alipay_h5';
        $appChannel->enabled = true;
        $appChannel->params = $cipher->encrypt(['appid' => 'own-appid']);

        $channelConfig = new PaymentChannelConfigEntity();
        $channelConfig->setUniqueIdentifier('channel-config-id');
        $channelConfig->providerTechnicalName = 'alipay';
        $channelConfig->channelTechnicalName = 'alipay_h5';
        $channelConfig->enabled = true;
        $channelConfig->params = $cipher->encrypt(['appid' => 'platform-appid']);

        $appProviderRepository = static::createStub(EntityRepository::class);
        $appProviderRepository->method('search')->willReturn($this->entityResult('payment_app_provider', $appProvider));

        $appChannelRepository = static::createStub(EntityRepository::class);
        $appChannelRepository->method('search')->willReturn($this->entityResult('payment_app_channel', $appChannel));

        $channelConfigRepository = static::createStub(EntityRepository::class);
        $channelConfigRepository->method('search')->willReturn($this->entityResult('payment_channel_config', $channelConfig));

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            $appProviderRepository,
            static::createStub(EntityRepository::class),
            $appChannelRepository,
            $channelConfigRepository,
            $cipher
        );
    }

    private function orderService(?StateMachineRegistry $stateMachine = null): PaymentOrderService
    {
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn(self::CREATED_STATE);

        $orderRepository = static::createStub(EntityRepository::class);
        $orderRepository->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            0,
            new PaymentOrderCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return new PaymentOrderService(
            $orderRepository,
            $this->configResolver($this->gateway()),
            $initialStateIdLoader,
            $stateMachine ?? static::createStub(StateMachineRegistry::class),
            new MockClock(self::NOW)
        );
    }

    /**
     * @return EntitySearchResult<EntityCollection<Entity>>
     */
    private function entityResult(string $entityName, Entity $entity): EntitySearchResult
    {
        return new EntitySearchResult(
            $entityName,
            1,
            new EntityCollection([$entity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function order(array $overrides = []): PaymentOrderEntity
    {
        $order = new PaymentOrderEntity();
        $order->setUniqueIdentifier('order-id');
        $order->id = 'order-id';
        $order->paymentNo = 'PG0001';
        $order->outTradeNo = 'app-trade-1';
        $order->paymentAppId = 'app-id';
        $order->providerTechnicalName = 'alipay';
        $order->channelTechnicalName = 'alipay_h5';
        $order->amount = 1000;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = self::CREATED_STATE;
        $order->returnUrl = 'https://return.example.test';

        foreach ($overrides as $property => $value) {
            // @phpstan-ignore property.dynamicName (the overrides name the properties of the entity on purpose)
            $order->$property = $value;
        }

        return $order;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = 'secret';
        $app->active = true;

        return $app;
    }
}
