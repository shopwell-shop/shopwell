<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
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
use Shopwell\Core\System\Payment\Gateway\QueryResult;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderPaidHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderQueryResultHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStateHandler;
use Shopwell\Core\System\Payment\Order\PaymentOrderStates;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
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
#[CoversClass(PaymentOrderQueryResultHandler::class)]
class PaymentOrderQueryResultHandlerTest extends TestCase
{
    private const NOW = '2024-01-01T00:00:00+00:00';

    /**
     * The same moment as {@see self::NOW}, in the format the payload carries it: the api and the
     * webhook speak the storage format of the platform, not iso 8601.
     */
    private const NOW_STORAGE = '2024-01-01 00:00:00.000';

    private const CREATED_STATE = 'state-created';

    private const OPENED_STATE = 'state-opened';

    private const PAID_STATE = 'state-paid';

    private const NOTIFY_URL = 'https://app.example.test/webhook';

    public function testPaidBooksThePaymentTheChannelReported(): void
    {
        $order = $this->order();
        $paid = $this->order(['paidAt' => $this->now(), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => self::PAID_STATE]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($paid));
        // the write of PaymentOrderPaidHandler::handle(), which cannot be mocked itself
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                $row = $data[0];

                static::assertSame('order-id', $row['id']);
                static::assertSame(1000, $row['paidAmount']);
                static::assertSame('channel-trade-1', $row['outPaymentNo']);
                static::assertSame(['trade' => 'raw'], $row['channelData']);

                return true;
            }));

        $transitions = [];
        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->exactly(2))
            ->method('transition')
            ->with(static::callback(static function (Transition $transition) use (&$transitions): bool {
                $transitions[] = $transition->getTransitionName();

                return true;
            }));

        $deliveryRepository = $this->deliveryRepository();
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                $row = $data[0];

                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_PAID, $row['event']);
                static::assertSame('channel-trade-1', $row['payload']['data']['out_payment_no']);
                static::assertSame(1000, $row['payload']['data']['amount']);

                return true;
            }));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_PAID, 'channel-trade-1', 1000, 'CNY', 'TRADE_SUCCESS', ['trade' => 'raw']));

        static::assertSame([PaymentOrderStates::ACTION_PROCESS, PaymentOrderStates::ACTION_PAY], $transitions);
    }

    /**
     * @return iterable<string, array{int|null, int}>
     */
    public static function mismatchedAmounts(): iterable
    {
        yield 'another amount' => [999, 999];
        yield 'no amount at all' => [null, 0];
    }

    #[DataProvider('mismatchedAmounts')]
    public function testPaidRaisesAnErrorAndWritesNothingWhenTheAmountDoesNotMatch(?int $reported, int $actual): void
    {
        $order = $this->order();

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $this->expectExceptionObject(PaymentException::amountMismatch('PG0001', 1000, $actual));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_PAID, 'channel-trade-1', $reported, 'CNY'));
    }

    public function testPaidRaisesAnErrorAndWritesNothingWhenTheCurrencyDoesNotMatch(): void
    {
        $order = $this->order();

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $this->expectExceptionObject(PaymentException::currencyMismatch('PG0001', 'CNY', 'JPY'));

        // the same number in another currency is not the payment of this order
        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_PAID, 'channel-trade-1', 1000, 'JPY'));
    }

    public function testPaidBooksThePaymentWhenTheCurrencyIsReportedInAnotherCase(): void
    {
        // `cny` and `CNY` are the same currency, so the case must not reject a real payment
        $this->assertThePaymentIsBooked('cny');
    }

    public function testPaidBooksThePaymentWhenTheChannelReportsNoCurrency(): void
    {
        // an interface that reports no currency cannot be disproved, and refusing the payment would
        // leave it unbooked forever
        $this->assertThePaymentIsBooked(null);
    }

    public function testClosedClosesTheOrderLocallyAndTellsTheApp(): void
    {
        $order = $this->order();
        $closed = $this->order(['closedAt' => $this->now(), 'stateId' => PaymentOrderStates::STATE_CLOSED]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($closed));
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);
                static::assertInstanceOf(\DateTimeInterface::class, $data[0]['closedAt']);

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_CLOSE));

        $deliveryRepository = $this->deliveryRepository();
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame(PaymentWebhookDispatcher::EVENT_ORDER_CLOSED, $data[0]['event']);
                static::assertSame([
                    'reason' => 'closed',
                    'state' => PaymentOrderStates::STATE_CLOSED,
                    'closed_at' => self::NOW_STORAGE,
                ], $data[0]['payload']['data']);

                return true;
            }));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_CLOSED));
    }

    public function testFailedMarksTheOrderFailedAndTellsTheApp(): void
    {
        $order = $this->order();
        $failed = $this->order(['stateId' => PaymentOrderStates::STATE_FAILED]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($failed));
        // a failed order is a state, not a timestamp - nothing is written on the order itself
        $orderRepository->expects($this->never())->method('update');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_FAIL));

        $deliveryRepository = $this->deliveryRepository();
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame([
                    'reason' => 'failed',
                    'state' => PaymentOrderStates::STATE_FAILED,
                    'closed_at' => null,
                ], $data[0]['payload']['data']);

                return true;
            }));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_FAILED));
    }

    public function testAbsentLeavesAnOrderAloneThatMayStillExpire(): void
    {
        $order = $this->order(['expireAt' => $this->now()->modify('+1 hour')]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');
        $orderRepository->expects($this->never())->method('search');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_ABSENT));
    }

    public function testAbsentExpiresAnOrderThatNeverReachedTheChannel(): void
    {
        $order = $this->order(['expireAt' => $this->now()->modify('-1 hour')]);
        $expired = $this->order(['closedAt' => $this->now(), 'stateId' => PaymentOrderStates::STATE_EXPIRED]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($expired));
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);
                static::assertInstanceOf(\DateTimeInterface::class, $data[0]['closedAt']);

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        // exactly one transition, so the close of an opened order cannot hide in here
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_EXPIRE));

        $deliveryRepository = $this->deliveryRepository();
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame([
                    'reason' => 'expired',
                    'state' => PaymentOrderStates::STATE_EXPIRED,
                    'closed_at' => self::NOW_STORAGE,
                ], $data[0]['payload']['data']);

                return true;
            }));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_ABSENT));
    }

    /**
     * The state machine only knows `created -> expired`, so an order the channel was handed may not
     * expire - expiring it would lose a late payment. It is closed instead.
     */
    public function testAbsentClosesAnOrderThatWasHandedToTheChannel(): void
    {
        $order = $this->order(['stateId' => self::OPENED_STATE, 'expireAt' => $this->now()->modify('-1 hour')]);
        $closed = $this->order(['closedAt' => $this->now(), 'stateId' => PaymentOrderStates::STATE_CLOSED]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($closed));
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame('order-id', $data[0]['id']);
                static::assertInstanceOf(\DateTimeInterface::class, $data[0]['closedAt']);

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        // one transition in total: the order is closed, it is never expired
        $stateMachine->expects($this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $t): bool => $t->getTransitionName() === PaymentOrderStates::ACTION_CLOSE));

        $deliveryRepository = $this->deliveryRepository();
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame([
                    'reason' => 'absent',
                    'state' => PaymentOrderStates::STATE_CLOSED,
                    'closed_at' => self::NOW_STORAGE,
                ], $data[0]['payload']['data']);

                return true;
            }));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_ABSENT));
    }

    public function testPendingWritesNothing(): void
    {
        $order = $this->order(['expireAt' => $this->now()->modify('-1 hour')]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->expects($this->never())->method('update');
        $orderRepository->expects($this->never())->method('search');

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->never())->method('transition');

        $deliveryRepository = static::createMock(EntityRepository::class);
        $deliveryRepository->expects($this->never())->method('create');

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_PENDING, 'channel-trade-1', 1000, 'CNY'));
    }

    /**
     * The payment is written and announced, whichever way the interface spelled its currency.
     */
    private function assertThePaymentIsBooked(?string $currency): void
    {
        $order = $this->order();
        $paid = $this->order(['paidAt' => $this->now(), 'paidAmount' => 1000, 'outPaymentNo' => 'channel-trade-1', 'stateId' => self::PAID_STATE]);

        $orderRepository = static::createMock(EntityRepository::class);
        $orderRepository->method('search')->willReturn($this->orderSearchResult($paid));
        $orderRepository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame(1000, $data[0]['paidAmount']);
                static::assertSame('channel-trade-1', $data[0]['outPaymentNo']);

                return true;
            }));

        $stateMachine = static::createMock(StateMachineRegistry::class);
        $stateMachine->expects($this->exactly(2))->method('transition');

        $deliveryRepository = $this->deliveryRepository();
        $deliveryRepository->expects($this->once())
            ->method('create')
            ->with(static::callback(static fn (array $data): bool => $data[0]['event'] === PaymentWebhookDispatcher::EVENT_ORDER_PAID));

        $this->handler($orderRepository, $stateMachine, $deliveryRepository)
            ->handle($order, new QueryResult(QueryResult::STATUS_PAID, 'channel-trade-1', 1000, $currency));
    }

    /**
     * @param EntityRepository<PaymentOrderCollection> $orderRepository
     * @param EntityRepository<PaymentWebhookDeliveryCollection>|null $deliveryRepository
     */
    private function handler(
        EntityRepository $orderRepository,
        StateMachineRegistry $stateMachine,
        ?EntityRepository $deliveryRepository = null
    ): PaymentOrderQueryResultHandler {
        $cipher = new PaymentCredentialCipher('test-secret');

        if (!$deliveryRepository instanceof EntityRepository) {
            $deliveryRepository = $this->deliveryRepository();
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

        return new PaymentOrderQueryResultHandler(
            $orderService,
            $webhookService,
            new PaymentOrderPaidHandler($orderService, $webhookService, static::createStub(EventDispatcherInterface::class)),
            new MockClock(self::NOW)
        );
    }

    /**
     * The handler never resolves a channel - closing, expiring and paying an order only write the
     * order - so the resolver is deliberately empty and every use of it would fail loudly.
     */
    private function configResolver(): PaymentConfigResolver
    {
        $providerRegistry = new PaymentProviderRegistry([]);

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
     * @return EntityRepository<PaymentWebhookDeliveryCollection>&MockObject
     */
    private function deliveryRepository(): EntityRepository
    {
        $repository = static::createMock(EntityRepository::class);
        $repository->method('search')->willReturnOnConsecutiveCalls($this->noDeliveryResult(), $this->deliverySearchResult());

        return $repository;
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
        $delivery->event = PaymentWebhookDispatcher::EVENT_ORDER_CLOSED;
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

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
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
        $order->stateId = self::CREATED_STATE;
        $order->notifyUrl = self::NOTIFY_URL;
        $order->paymentApp = $this->app();

        if (\array_key_exists('stateId', $overrides)) {
            $order->stateId = (string) $overrides['stateId'];
        }

        if (\array_key_exists('expireAt', $overrides)) {
            $expireAt = $overrides['expireAt'];
            static::assertInstanceOf(\DateTimeInterface::class, $expireAt);
            $order->expireAt = $expireAt;
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
