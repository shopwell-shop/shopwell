<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

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
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryCollection;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;
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
#[CoversClass(PaymentOrderPaidHandler::class)]
class PaymentOrderPaidHandlerTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    /**
     * The same moment as {@see self::NOW}, in the format the payload carries it: the api and the
     * webhook speak the storage format of the platform, not iso 8601.
     */
    private const NOW_STORAGE = '2024-01-01 00:00:00.000';

    private const CREATED_STATE = 'state-created';

    private const NOTIFY_URL = 'https://app.example.test/webhook';

    public function testHandleOpensThenPaysTheOrderWithTheAmountTheChannelReported(): void
    {
        $order = $this->order();
        $paid = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid']);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($paid));
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                $row = $data[0];

                static::assertSame('order-id', $row['id']);
                static::assertSame(1000, $row['paidAmount']);
                static::assertSame('channel-trade-1', $row['outPaymentNo']);
                static::assertSame(['trade' => 'raw'], $row['channelData']);
                static::assertInstanceOf(\DateTimeInterface::class, $row['paidAt']);

                return true;
            }));

        $transitions = [];
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->exactly(2))
            ->method('transition')
            ->with(static::callback(static function (Transition $transition) use (&$transitions): bool {
                $transitions[] = $transition->getTransitionName();

                return \in_array($transition->getTransitionName(), [PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::ACTION_PAY], true);
            }));

        $handler = $this->handler($orderRepository, $stateMachine);

        static::assertSame($paid, $handler->handle($order, 1000, 'channel-trade-1', ['trade' => 'raw']));

        // the state machine only allows a payment from `opened`, so the order has to be opened first
        static::assertSame([PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::ACTION_PAY], $transitions);
    }

    public function testHandleAnnouncesAndNotifiesWithTheOrderItReloaded(): void
    {
        $order = $this->order();
        $paid = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid']);

        $orderRepository = static::createStub(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($paid));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (object $event) use ($paid): bool {
                static::assertInstanceOf(PaymentOrderPaidEvent::class, $event);
                // the announcement carries what was written, not the entity the caller handed in
                static::assertSame($paid, $event->getPaymentOrder());

                return true;
            }));

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(function (array $data): bool {
                $row = $data[0];

                // enqueue() was called with PaymentWebhookDispatcher::EVENT_ORDER_PAID
                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $row['event']);
                static::assertSame('order-id', $row['paymentOrderId']);
                static::assertSame(self::NOTIFY_URL, $row['url']);
                static::assertSame(PaymentWebhookDeliveryEntity::STATUS_PENDING, $row['status']);

                // the seven keys of the paid payload
                static::assertSame([
                    'amount' => 1000,
                    'currency' => 'CNY',
                    'provider' => 'alipay',
                    'channel' => 'alipay_h5',
                    'out_payment_no' => 'channel-trade-1',
                    'paid_at' => self::NOW_STORAGE,
                    'state' => PaymentOrderStates::STATE_PAID,
                ], $row['payload']['data']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls($this->noDeliveryResult(), $this->deliverySearchResult());

        $handler = $this->handler($orderRepository, static::createStub(StateMachineRegistry::class), $deliveryRepository, $eventDispatcher);

        $result = $handler->handle($order, 1000, 'channel-trade-1', ['trade' => 'raw']);

        // the caller gets the reloaded order, so it never works with a stale copy
        static::assertSame($paid, $result);
        static::assertNotSame($order, $result);
    }

    public function testHandleDoesNotAnnounceOrNotifyWhenTheOrderCannotBeWritten(): void
    {
        $order = $this->order();

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($order));
        // markPaid() propagates what the storage reports, e.g. the order was paid by another thread
        $orderRepository->expects($this->once())
            ->method('update')
            ->willThrowException(PaymentException::orderNotFound('order-id'));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())->method('dispatch');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $handler = $this->handler($orderRepository, static::createStub(StateMachineRegistry::class), $deliveryRepository, $eventDispatcher);

        $this->expectExceptionObject(PaymentException::orderNotFound('order-id'));

        $handler->handle($order, 1000, 'channel-trade-1', ['trade' => 'raw']);
    }

    public function testAnnounceDispatchesAndNotifiesWithoutWriting(): void
    {
        $paid = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid']);

        // announcing an already written payment must not write or migrate anything
        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->orderSearchResult($paid));
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(PaymentOrderPaidEvent::class));

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                $row = $data[0];

                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $row['event']);
                static::assertSame([
                    'amount' => 1000,
                    'currency' => 'CNY',
                    'provider' => 'alipay',
                    'channel' => 'alipay_h5',
                    'out_payment_no' => 'channel-trade-1',
                    'paid_at' => self::NOW_STORAGE,
                    'state' => PaymentOrderStates::STATE_PAID,
                ], $row['payload']['data']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls($this->noDeliveryResult(), $this->deliverySearchResult());

        $handler = $this->handler($orderRepository, $stateMachine, $deliveryRepository, $eventDispatcher);

        static::assertSame($paid, $handler->announce($paid));
    }

    public function testAnnounceUsesTheReloadedOrder(): void
    {
        $input = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'stateId' => 'state-paid']);
        $reloaded = $this->order(['paidAt' => new \DateTimeImmutable(self::NOW), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => 'state-paid']);
        // the storage is the truth, so the announced order is told apart from the handed in one
        $reloaded->paymentNo = 'PG0002';

        $orderRepository = static::createStub(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($reloaded));

        $eventDispatcher = static::createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(static function (object $event) use ($reloaded): bool {
                static::assertInstanceOf(PaymentOrderPaidEvent::class, $event);
                static::assertSame($reloaded, $event->getPaymentOrder());

                return true;
            }));

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame('PG0002', $data[0]['payload']['payment_no']);

                return true;
            }));
        $deliveryRepository->method('search')->willReturnOnConsecutiveCalls($this->noDeliveryResult(), $this->deliverySearchResult());

        $handler = $this->handler($orderRepository, static::createStub(StateMachineRegistry::class), $deliveryRepository, $eventDispatcher);

        $result = $handler->announce($input);

        static::assertSame($reloaded, $result);
        static::assertNotSame($input, $result);
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<PaymentWebhookDeliveryCollection>|null $deliveryRepository
     */
    private function handler(
        EntityRepository $orderRepository,
        StateMachineRegistry $stateMachine,
        ?EntityRepository $deliveryRepository = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ): PaymentOrderPaidHandler {
        $cipher = new PaymentCredentialCipher('test-secret');

        if (!$deliveryRepository instanceof EntityRepository) {
            $deliveryRepository = static::createStub(EntityRepository::class);
            $deliveryRepository->method('search')->willReturnOnConsecutiveCalls($this->noDeliveryResult(), $this->deliverySearchResult());
        }

        $orderService = new PaymentOrderService(
            $orderRepository,
            $this->configResolver(),
            $this->initialStateIdLoader(),
            new PaymentOrderStateHandler($stateMachine),
            new MockClock(self::NOW)
        );

        $webhookService = new PaymentWebhookDispatcher(
            $deliveryRepository,
            $this->appRepository(),
            new PaymentWebhookSigner(new PaymentSignatureService(), $cipher, new MockClock(self::NOW)),
            static::createStub(HttpClientInterface::class),
            new MockClock(self::NOW),
            static::createStub(LoggerInterface::class)
        );

        return new PaymentOrderPaidHandler(
            $orderService,
            $webhookService,
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class)
        );
    }

    private function configResolver(): PaymentConfigResolver
    {
        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            channels: [],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        $providerRegistry = new PaymentProviderRegistry([$provider]);

        return new PaymentConfigResolver(
            $providerRegistry,
            new PaymentChannelRegistry($providerRegistry),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            new PaymentCredentialCipher('test-secret')
        );
    }

    private function initialStateIdLoader(): InitialStateIdLoader
    {
        $loader = static::createStub(InitialStateIdLoader::class);
        $loader->method('get')->willReturn(self::CREATED_STATE);

        return $loader;
    }

    /**
     * @return EntitySearchResult<PaymentOrderCollection>
     */
    private function orderSearchResult(PaymentOrderEntity ...$orders): EntitySearchResult
    {
        return new EntitySearchResult(
            PaymentOrderEntity::ENTITY_NAME,
            \count($orders),
            new PaymentOrderCollection($orders),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentWebhookDeliveryCollection>
     */
    private function noDeliveryResult(): EntitySearchResult
    {
        return new EntitySearchResult(
            'payment_webhook_delivery',
            0,
            new PaymentWebhookDeliveryCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @return EntitySearchResult<PaymentWebhookDeliveryCollection>
     */
    private function deliverySearchResult(): EntitySearchResult
    {
        $delivery = new PaymentWebhookDeliveryEntity();
        $delivery->setUniqueIdentifier('delivery-id');
        $delivery->id = 'delivery-id';
        $delivery->paymentOrderId = 'order-id';
        $delivery->paymentAppId = 'app-id';
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_PAID;
        $delivery->url = self::NOTIFY_URL;

        return new EntitySearchResult(
            'payment_webhook_delivery',
            1,
            new PaymentWebhookDeliveryCollection([$delivery]),
            null,
            new Criteria(),
            Context::createDefaultContext()
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
        $order->paidAmount = 0;
        $order->refundedAmount = 0;
        $order->currency = 'CNY';
        $order->subject = 'An order';
        $order->attach = 'attach-data';
        $order->stateId = self::CREATED_STATE;
        $order->notifyUrl = self::NOTIFY_URL;
        $order->paymentApp = $this->app();

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

        if (\array_key_exists('outPaymentNo', $overrides)) {
            $order->outPaymentNo = (string) $overrides['outPaymentNo'];
        }

        return $order;
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
}
