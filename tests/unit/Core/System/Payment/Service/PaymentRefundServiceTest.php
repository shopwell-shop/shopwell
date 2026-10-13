<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Service;

use Payum\Core\GatewayFactoryInterface;
use Payum\Core\GatewayInterface;
use Payum\Core\Request\Refund as RefundRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundCollection;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Event\PaymentRefundFinishedEvent;
use Shopwell\Core\System\Payment\Gateway\NotificationResult;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\RefundResult;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentRefundStates;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Service\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Service\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Service\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Service\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Service\PaymentOrderService;
use Shopwell\Core\System\Payment\Service\PaymentRefundService;
use Shopwell\Core\System\Payment\Service\PaymentSignatureService;
use Shopwell\Core\System\Payment\Service\PaymentWebhookService;
use Shopwell\Core\System\Payment\Struct\PaymentRefundRequest;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRefundService::class)]
class PaymentRefundServiceTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    private const CREATED_STATE = 'state-created';

    public function testRefundThrowsWhenTheOrderWasNeverPaid(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('create');

        $service = $this->refundService($refundRepository, $this->gateway(null));

        $this->expectExceptionObject(PaymentException::refundNotAllowed('PG0001'));

        $service->refund($this->app(), $this->order(), $this->refundRequest(1000));
    }

    public function testRefundThrowsForANonPositiveAmount(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('create');

        $service = $this->refundService($refundRepository, $this->gateway(null));

        $this->expectExceptionObject(PaymentException::invalidAmount(0));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(0));
    }

    public function testRefundThrowsWhenTheAmountExceedsTheRefundableAmount(): void
    {
        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('create');

        $order = $this->paidOrder();

        $service = $this->refundService($refundRepository, $this->gateway(null));

        $this->expectExceptionObject(PaymentException::refundAmountExceedsOrder('PG0001', 1001, 1000));

        $service->refund($this->app(), $order, $this->refundRequest(1001));
    }

    public function testRefundReturnsTheExistingRefundForAKnownOutRefundNo(): void
    {
        $existing = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($existing));
        $refundRepository->expects($this->never())->method('create');

        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');

        $service = $this->refundService($refundRepository, $gateway);

        static::assertSame($existing, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    public function testRefundSettlesSynchronouslyAndNotifiesTheApp(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('refund-id', $data[0]['id']);
                static::assertSame('channel-refund-1', $data[0]['outRefundId']);

                $refundedAt = $data[0]['refundedAt'];
                static::assertInstanceOf(\DateTimeInterface::class, $refundedAt);
                static::assertSame(self::NOW, $refundedAt->format(\DateTimeInterface::ATOM));

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_FINISH));

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentRefundFinishedEvent::class));

        $service = $this->refundService(
            $refundRepository,
            $this->gateway(PaymentRefundStates::STATE_FINISHED, 'channel-refund-1'),
            $this->orderService($this->orderRepository($this->paidOrder()), $orderStateMachine),
            $refundStateMachine,
            $eventDispatcher
        );

        static::assertSame($refund, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    public function testRefundStaysProcessingWhenTheChannelWorksAsynchronously(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('channel-refund-1', $data[0]['outRefundId']);
                static::assertArrayNotHasKey('refundedAt', $data[0]);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_PROCESS));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $service = $this->refundService(
            $refundRepository,
            $this->gateway(PaymentRefundStates::STATE_PROCESSING, 'channel-refund-1'),
            $this->orderService($this->orderRepository($this->paidOrder())),
            $refundStateMachine,
            $eventDispatcher
        );

        static::assertSame($refund, $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000)));
    }

    public function testRefundRecordsTheChannelFailureAndThrows(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturnCallback(
            fn (Criteria $criteria): EntitySearchResult => $criteria->getIds() === []
                ? $this->refundResult()
                : $this->refundResult($refund)
        );
        $refundRepository->expects($this->once())->method('create');
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame('boom', $data[0]['responsePayload']['exception']);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_FAIL));

        $service = $this->refundService(
            $refundRepository,
            $this->failingGateway(),
            $this->orderService($this->orderRepository($this->paidOrder())),
            $refundStateMachine
        );

        $this->expectExceptionObject(PaymentException::gatewayError('alipay_h5', 'boom'));

        $service->refund($this->app(), $this->paidOrder(), $this->refundRequest(1000));
    }

    public function testFindByRefundNoReturnsTheRefund(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($refund));

        static::assertSame($refund, $this->refundService($refundRepository, $this->gateway(null))->findByRefundNo('PR0001'));
    }

    public function testFindByRefundNoReturnsNullWhenUnknown(): void
    {
        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());

        static::assertNull($this->refundService($refundRepository, $this->gateway(null))->findByRefundNo('PR0001'));
    }

    public function testGetRefundThrowsWhenItDoesNotExist(): void
    {
        $refundRepository = static::createStub(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult());

        $this->expectExceptionObject(PaymentException::refundNotFound('missing-id'));

        $this->refundService($refundRepository, $this->gateway(null))->getRefund('missing-id');
    }

    public function testApplyNotificationIsIdempotentOnceTheRefundWasSettled(): void
    {
        $refund = $this->refundEntity(['refundedAt' => new \DateTimeImmutable(self::NOW)]);

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->never())->method('update');
        $refundRepository->expects($this->never())->method('search');

        $this->refundService($refundRepository, $this->gateway(null))
            ->applyNotification($refund, $this->paidOrder(), new NotificationResult(true, true, amount: 1000, raw: ['status' => 'done']));
    }

    public function testApplyNotificationRecordsTheAnswerWithoutSettlingWhenTheChannelDidNotSucceed(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                static::assertSame(['status' => 'processing'], $data[0]['responsePayload']);
                static::assertArrayNotHasKey('refundedAt', $data[0]);

                return true;
            }));

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->never())->method('transition');

        $this->refundService($refundRepository, $this->gateway(null), null, $refundStateMachine)
            ->applyNotification($refund, $this->paidOrder(), new NotificationResult(true, false, amount: 1000, raw: ['status' => 'processing']));
    }

    public function testApplyNotificationSettlesTheRefundWhenTheChannelConfirmed(): void
    {
        $refund = $this->refundEntity();

        $refundRepository = static::createMock(EntityRepository::class);
        $refundRepository->method('search')->willReturn($this->refundResult($refund));
        $refundRepository->expects($this->exactly(2))->method('update');

        $refundStateMachine = static::createMock(StateMachineRegistry::class);
        $refundStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentRefundStates::ACTION_FINISH));

        $orderStateMachine = static::createMock(StateMachineRegistry::class);
        $orderStateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_REFUND));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $this->refundService(
            $refundRepository,
            $this->gateway(null),
            $this->orderService($this->orderRepository($this->paidOrder()), $orderStateMachine),
            $refundStateMachine,
            $eventDispatcher
        )->applyNotification($refund, $this->paidOrder(), new NotificationResult(true, true, outRefundId: 'channel-refund-1', amount: 1000, raw: ['status' => 'done']));
    }

    /**
     * @param EntityRepository<PaymentRefundCollection> $refundRepository
     */
    private function refundService(
        EntityRepository $refundRepository,
        GatewayInterface $gateway,
        ?PaymentOrderService $orderService = null,
        ?StateMachineRegistry $refundStateMachine = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ): PaymentRefundService {
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-open');

        $cipher = new PaymentCredentialCipher('test-secret');

        $deliveryRepository = static::createStub(EntityRepository::class);
        $deliveryRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_webhook_delivery',
            0,
            new PaymentWebhookDeliveryCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $appRepository = static::createStub(EntityRepository::class);
        $appRepository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            0,
            new PaymentAppCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $webhookService = new PaymentWebhookService(
            $deliveryRepository,
            $appRepository,
            new PaymentSignatureService(),
            $cipher,
            static::createStub(HttpClientInterface::class),
            new MockClock(self::NOW),
            static::createStub(LoggerInterface::class)
        );

        return new PaymentRefundService(
            $refundRepository,
            new PaymentGatewayFactory(),
            $this->configResolver($gateway),
            $orderService ?? $this->orderService($this->orderRepository($this->paidOrder())),
            $webhookService,
            new PaymentNotifyUrlBuilder(static::createStub(RequestStack::class), 'https://base.example.test'),
            $refundStateMachine ?? static::createStub(StateMachineRegistry::class),
            $initialStateIdLoader,
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            new MockClock(self::NOW)
        );
    }

    private function gateway(?string $status, ?string $outRefundId = null): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (object $request) use ($status, $outRefundId): void {
            static::assertInstanceOf(RefundRequest::class, $request);

            $model = $request->getModel();
            static::assertInstanceOf(\ArrayObject::class, $model);

            $model->offsetSet(PaymentGatewayModel::REQUEST, ['out_refund_no' => 'app-refund-1']);
            $model->offsetSet(PaymentGatewayModel::RESPONSE, ['code' => 'ok']);

            if ($status !== null) {
                $model->offsetSet(PaymentGatewayModel::RESULT, new RefundResult($status, $outRefundId));
            }
        });

        return $gateway;
    }

    private function failingGateway(): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException(new \RuntimeException('boom'));

        return $gateway;
    }

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

    /**
     * @param EntityRepository<PaymentOrderCollection>|null $orderRepository
     */
    private function orderService(?EntityRepository $orderRepository = null, ?StateMachineRegistry $stateMachine = null): PaymentOrderService
    {
        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn(self::CREATED_STATE);

        return new PaymentOrderService(
            $orderRepository ?? $this->orderRepository($this->paidOrder()),
            $this->configResolver($this->gateway(null)),
            $initialStateIdLoader,
            $stateMachine ?? static::createStub(StateMachineRegistry::class),
            new MockClock(self::NOW)
        );
    }

    /**
     * @return EntityRepository<PaymentOrderCollection>
     */
    private function orderRepository(PaymentOrderEntity ...$orders): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            \count($orders),
            new PaymentOrderCollection($orders),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
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
     * @return EntitySearchResult<PaymentRefundCollection>
     */
    private function refundResult(PaymentRefundEntity ...$refunds): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentRefundEntity::ENTITY_NAME,
            \count($refunds),
            new PaymentRefundCollection($refunds),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function refundEntity(array $overrides = []): PaymentRefundEntity
    {
        $refund = new PaymentRefundEntity();
        $refund->setUniqueIdentifier('refund-id');
        $refund->id = 'refund-id';
        $refund->refundNo = 'PR0001';
        $refund->paymentOrderId = 'order-id';
        $refund->outRefundNo = 'app-refund-1';
        $refund->amount = 1000;
        $refund->currency = 'CNY';
        $refund->stateId = 'state-open';

        if (\array_key_exists('refundedAt', $overrides)) {
            $refundedAt = $overrides['refundedAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $refundedAt);
            $refund->refundedAt = $refundedAt;
        }

        return $refund;
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

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('paidAmount', $overrides)) {
            $order->paidAmount = (int) $overrides['paidAmount'];
        }

        if (\array_key_exists('refundedAmount', $overrides)) {
            $order->refundedAmount = (int) $overrides['refundedAmount'];
        }

        if (\array_key_exists('outPaymentNo', $overrides)) {
            $order->outPaymentNo = (string) $overrides['outPaymentNo'];
        }

        return $order;
    }

    private function paidOrder(): PaymentOrderEntity
    {
        return $this->order([
            'paidAt' => new \DateTimeImmutable(self::NOW),
            'paidAmount' => 1000,
            'refundedAmount' => 0,
            'outPaymentNo' => 'channel-trade-1',
            'stateId' => 'state-paid',
        ]);
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

    private function refundRequest(int $amount): PaymentRefundRequest
    {
        return new PaymentRefundRequest('app-refund-1', $amount);
    }
}
