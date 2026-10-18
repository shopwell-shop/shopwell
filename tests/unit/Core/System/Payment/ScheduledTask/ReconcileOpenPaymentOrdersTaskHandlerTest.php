<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use Payum\Core\GatewayInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\App\PaymentSignatureService;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderReconciler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTaskHandler;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDispatcher;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookSigner;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentOrdersTaskHandler::class)]
#[CoversClass(PaymentOrderReconciler::class)]
class ReconcileOpenPaymentOrdersTaskHandlerTest extends TestCase
{
    public function testRunAsksTheInterfacesAboutTheDueOrders(): void
    {
        // the reconcile is what the handler runs, so its first read is the proof the handler ran it
        $stateRepository = static::createMock(EntityRepository::class);
        $stateRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            'state_machine_state',
            1,
            new StateMachineStateCollection([$this->openedState()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())->method('search')->willReturn(new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            0,
            new PaymentOrderCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $handler = new ReconcileOpenPaymentOrdersTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $this->reconciler($orderRepository, $stateRepository)
        );

        $handler->run();
    }

    public function testReconcileAnnouncesAnOrderThatWasPaidMeanwhile(): void
    {
        $paid = $this->order(['paidAt' => new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 'stateId' => 'state-paid']);

        // the payment is already written, so the reconciler must not write it again - but the
        // platform may never have been told about it
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                PaymentOrderEntity::ENTITY_NAME,
                1,
                new PaymentOrderCollection([$paid]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));
        $orderRepository->expects($this->never())->method('update');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $data[0]['event']);
                static::assertSame('order-id', $data[0]['paymentOrderId']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls(
            $this->deliverySearchResult(),
            $this->deliverySearchResult($this->delivery())
        );

        $this->reconciler($orderRepository, static::createStub(EntityRepository::class), $eventDispatcher, $deliveryRepository)
            ->reconcile($paid);
    }

    public function testReconcileLeavesAClosedOrExpiredOrderAlone(): void
    {
        $closed = $this->order(['closedAt' => new \DateTimeImmutable('2024-01-01T00:00:00+00:00'), 'stateId' => 'state-closed']);

        // closed and expired orders are not payable either, but unlike a paid one there is nothing
        // left to announce - the order is not even read again
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('search');
        $orderRepository->expects($this->never())->method('update');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $this->reconciler($orderRepository, static::createStub(EntityRepository::class), $eventDispatcher, $deliveryRepository)
            ->reconcile($closed);
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     * @param EntityRepository<PaymentWebhookDeliveryCollection>|null $deliveryRepository
     */
    private function reconciler(
        EntityRepository $orderRepository,
        EntityRepository $stateRepository,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?EntityRepository $deliveryRepository = null
    ): PaymentOrderReconciler {
        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $gatewayFactory->method('createForPayment')->willReturn(static::createStub(GatewayInterface::class));

        $channel = new PaymentChannel('alipay_h5', 'alipay', 'Alipay H5', PaymentFlow::REDIRECT);

        $provider = new PaymentProvider(
            'alipay',
            'Alipay',
            [],
            ['alipay_h5' => $channel],
            $gatewayFactory,
            static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        $configResolver = new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret')
        );

        $initialStateIdLoader = static::createStub(InitialStateIdLoader::class);
        $initialStateIdLoader->method('get')->willReturn('state-created');

        $orderService = new PaymentOrderService(
            $orderRepository,
            $configResolver,
            $initialStateIdLoader,
            new PaymentOrderStateHandler(static::createStub(StateMachineRegistry::class)),
            new MockClock('2024-01-01T00:00:00+00:00')
        );

        $webhookService = new PaymentWebhookDispatcher(
            $deliveryRepository ?? static::createStub(EntityRepository::class),
            $this->appRepository(),
            new PaymentWebhookSigner(new PaymentSignatureService(), new PaymentCredentialCipher('test-secret'), new MockClock('2024-01-01T00:00:00+00:00')),
            static::createStub(HttpClientInterface::class),
            new MockClock('2024-01-01T00:00:00+00:00'),
            static::createStub(LoggerInterface::class)
        );

        $clock = new MockClock('2024-01-01T00:00:00+00:00');
        $paidHandler = new PaymentOrderPaidHandler($orderService, $webhookService, $eventDispatcher ?? static::createStub(EventDispatcherInterface::class));

        return new PaymentOrderReconciler(
            $orderRepository,
            $stateRepository,
            $configResolver,
            $orderService,
            $paidHandler,
            new PaymentOrderQueryResultHandler($orderService, $webhookService, $paidHandler, $clock),
            $clock,
            static::createStub(LoggerInterface::class)
        );
    }

    /**
     * @return EntityRepository<PaymentAppCollection>
     */
    private function appRepository(): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'payment_app',
            1,
            new PaymentAppCollection([$this->app()]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        return $repository;
    }

    private function app(): PaymentAppEntity
    {
        $app = new PaymentAppEntity();
        $app->setUniqueIdentifier('app-id');
        $app->id = 'app-id';
        $app->technicalName = 'demo-app';
        $app->appSecret = (new PaymentCredentialCipher('test-secret'))->encryptValue('secret');
        $app->active = true;

        return $app;
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
        $order->paidAmount = 1000;
        $order->refundedAmount = 0;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->stateId = 'state-opened';
        $order->notifyUrl = 'https://app.example.test/webhook';
        $order->paymentApp = $this->app();

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('paidAt', $overrides)) {
            $paidAt = $overrides['paidAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $paidAt);
            $order->paidAt = $paidAt;
        }

        if (\array_key_exists('closedAt', $overrides)) {
            $closedAt = $overrides['closedAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $closedAt);
            $order->closedAt = $closedAt;
        }

        return $order;
    }

    /**
     * @return EntitySearchResult<PaymentWebhookDeliveryCollection>
     */
    private function deliverySearchResult(PaymentWebhookDeliveryEntity ...$deliveries): EntitySearchResult
    {
        return new EntitySearchResult(
            'payment_webhook_delivery',
            \count($deliveries),
            new PaymentWebhookDeliveryCollection($deliveries),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function delivery(): PaymentWebhookDeliveryEntity
    {
        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_PAID;
        $delivery->url = 'https://app.example.test/webhook';

        return $delivery;
    }

    private function openedState(): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setId('state-opened');

        return $state;
    }
}
