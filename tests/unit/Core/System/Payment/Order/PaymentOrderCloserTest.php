<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Generic;
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
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\CloseResult;
use Shopwell\Core\System\Payment\Gateway\PaymentClose;
use Shopwell\Core\System\Payment\Gateway\PaymentCloser;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\PaymentQuery;
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\PaymentOrderCloser;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentOrderCloser::class)]
class PaymentOrderCloserTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    /**
     * What the fake channel was asked, in order.
     *
     * @var list<class-string>
     */
    private array $asked = [];

    public function testAClosedTradeClosesTheOrderLocally(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(function (array $data): bool {
                /** @var array{id: string, closedAt: \DateTimeInterface} $row */
                $row = $data[0];

                static::assertSame('order-id', $row['id']);
                static::assertSame('2024-01-01 00:00:00', $row['closedAt']->format('Y-m-d H:i:s'));

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_CLOSE));

        $result = $this->closer($orderRepository, $this->gateway(CloseResult::STATUS_CLOSED), $stateMachine)
            ->close($this->order());

        static::assertSame(CloseResult::STATUS_CLOSED, $result->status);
    }

    public function testATradeTheChannelDoesNotKnowIsClosedLocallyAsWell(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())->method('transition');

        $result = $this->closer($orderRepository, $this->gateway(CloseResult::STATUS_NOT_FOUND), $stateMachine)
            ->close($this->order());

        static::assertSame(CloseResult::STATUS_NOT_FOUND, $result->status);
    }

    /**
     * The close turned up a payment: booking it is the correct outcome, so it happens before the
     * caller is told that the order was not closed - an app must never believe a paid order is off.
     */
    public function testATradeThatIsPaidIsBookedAndTheCloseIsRefused(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $gateway = $this->gateway(CloseResult::STATUS_ALREADY_PAID);

        $this->expectExceptionObject(PaymentException::orderAlreadyPaid('PG0001'));

        try {
            $this->closer($orderRepository, $gateway, $stateMachine)->close($this->order());
        } finally {
            // the reconciliation asked the channel about the payment exactly once - and, because the
            // channel answered "pending", it wrote nothing
            static::assertSame([PaymentClose::class, PaymentQuery::class], $this->asked);
        }
    }

    public function testAGatewayFailureLeavesTheOrderOpenAndSurfaces(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $failure = PaymentException::gatewayError('alipay_h5', 'the channel is down');

        $this->expectExceptionObject($failure);

        $this->closer($orderRepository, $this->failingGateway($failure), $stateMachine)->close($this->order());
    }

    public function testAPaidOrderIsRefusedWithoutTouchingTheChannel(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $gateway = $this->failingGateway(new \RuntimeException('the channel must not be asked'));

        $this->expectExceptionObject(PaymentException::orderAlreadyPaid('PG0001'));

        $this->closer($orderRepository, $gateway, $stateMachine)
            ->close($this->order(['paidAt' => new \DateTimeImmutable(self::NOW)]));
    }

    public function testAClosedOrderIsRefusedWithoutTouchingTheChannel(): void
    {
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $gateway = $this->failingGateway(new \RuntimeException('the channel must not be asked'));

        $this->expectExceptionObject(PaymentException::orderAlreadyClosed('PG0001'));

        $this->closer($orderRepository, $gateway, $stateMachine)
            ->close($this->order(['closedAt' => new \DateTimeImmutable(self::NOW)]));
    }

    /**
     * The gateway of the test channel: a close is answered with the given status, a query - which the
     * reconciliation runs after a paid answer - with "pending", so nothing is written and the test can
     * count the calls.
     */
    private function gateway(string $closeStatus): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(function (Generic $request) use ($closeStatus): void {
            $this->asked[] = $request::class;

            $model = $request->getModel();
            \assert($model instanceof PaymentGatewayModel);

            if ($request instanceof PaymentClose) {
                $model->recordResponse(['status' => $closeStatus]);
                $model->offsetSet(PaymentGatewayModel::RESULT, new CloseResult($closeStatus, $closeStatus));

                return;
            }

            $model->offsetSet(PaymentGatewayModel::RESULT, new QueryResult(QueryResult::STATUS_PENDING, 'channel-trade-1', 1000, 'CNY'));
        });

        return $gateway;
    }

    private function failingGateway(\Throwable $failure): GatewayInterface
    {
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willThrowException($failure);

        return $gateway;
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     */
    private function closer(EntityRepository $orderRepository, GatewayInterface $gateway, StateMachineRegistry $stateMachine): PaymentOrderCloser
    {
        $configResolver = $this->configResolver($gateway);
        $clock = new MockClock(self::NOW);

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $orderService = new PaymentOrderService(
            $orderRepository,
            $configResolver,
            $initialStateIdLoader,
            new PaymentOrderStateHandler($stateMachine),
            $clock,
        );

        $webhooks = new PaymentWebhookDispatcher(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test-secret'), $clock),
            static::createStub(HttpClientInterface::class),
            $clock,
            static::createStub(LoggerInterface::class),
        );

        $paidHandler = new PaymentOrderPaidHandler($orderService, $webhooks, static::createStub(EventDispatcherInterface::class));

        return new PaymentOrderCloser(
            new PaymentCloser(static::createStub(EntityRepository::class), $configResolver, $clock),
            $orderService,
            new PaymentOrderReconciler(
                $orderRepository,
                static::createStub(EntityRepository::class),
                $configResolver,
                $orderService,
                $paidHandler,
                new PaymentOrderQueryResultHandler($orderService, $webhooks, $paidHandler, $clock),
                $clock,
                static::createStub(LoggerInterface::class),
            ),
        );
    }

    private function configResolver(GatewayInterface $gateway): PaymentConfigResolver
    {
        $cipher = new PaymentCredentialCipher('test-secret');

        $channel = new PaymentChannel(
            technicalName: 'alipay_h5',
            providerTechnicalName: 'alipay',
            label: 'Alipay H5',
            flow: PaymentFlow::REDIRECT,
        );

        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn($gateway);

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: ['alipay_h5' => $channel],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            // alipay closes and queries; the reconciler of the closer needs the query as well
            capabilities: [PaymentCapability::CAPTURE, PaymentCapability::CLOSE, PaymentCapability::QUERY],
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $appProvider = new PaymentAppProviderEntity();
        $appProvider->setUniqueIdentifier('app-provider-id');
        $appProvider->paymentAppId = 'app-id';
        $appProvider->providerTechnicalName = 'alipay';
        $appProvider->enabled = true;
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
        $order->stateId = 'state-opened';
        $order->returnUrl = 'https://return.example.test';
        $order->paymentApp = $this->app();

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
