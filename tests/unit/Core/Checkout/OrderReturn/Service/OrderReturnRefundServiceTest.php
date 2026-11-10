<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundDestination;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundExecutor;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundSource;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentRefund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentRefundService;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway\PaymentGatewayFixture;

/**
 * The payout itself is not implemented here, so the tests are about the three decisions this service
 * takes: which payment of the order is refunded at all, that the return number is the number the
 * gateway books the payout under, and when the return moves on.
 *
 * The payment services are `final` and internal, so they can not be doubled. The payout of a replayed
 * out refund number is answered before any of their collaborators are used, which is why an instance
 * without a constructor is all that is needed for those - and why a test that passes proves the
 * lookup was made with the return number of the aftersale.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnRefundService::class)]
class OrderReturnRefundServiceTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testAReturnThatWasAlreadyHandedToTheGatewayIsNotPaidOutTwice(): void
    {
        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->never())->method('refund');

        $repository = $this->returnRepository();

        $this->service($this->paymentRefundService(null), $stateHandler, $repository)->refund(
            $this->orderReturn(paymentRefundNo: 'RF-1'),
            $this->order(),
            $this->context
        );

        static::assertSame([], $repository->updates);
    }

    public function testAReturnWithoutAnAmountIsNotHandedToTheGateway(): void
    {
        $this->expectExceptionObject(OrderReturnException::refundNotPossible('RT-1', 'the return has no amount to refund.'));

        $this->service()->refund($this->orderReturn(refundAmount: 0), $this->order(), $this->context);
    }

    public function testAnOrderWithoutACollectedPaymentCanNotBeRefunded(): void
    {
        $this->expectExceptionObject(OrderReturnException::refundNotPossible('RT-1', 'the order has no collected payment.'));

        $this->service()->refund($this->orderReturn(), $this->order(transactionId: null), $this->context);
    }

    public function testAPaymentThatWasNeverPaidCanNotBeRefunded(): void
    {
        $this->expectExceptionObject(OrderReturnException::refundNotPossible('RT-1', 'the order has no collected payment.'));

        $this->service(paymentOrder: $this->paymentOrder(paid: false))
            ->refund($this->orderReturn(), $this->order(), $this->context);
    }

    public function testAPaymentWhoseChannelIsGoneCanNotBeRefunded(): void
    {
        $this->expectExceptionObject(OrderReturnException::refundNotPossible(
            'RT-1',
            'the payment app of the collected payment does not exist anymore.'
        ));

        $this->service(apps: [])->refund($this->orderReturn(), $this->order(), $this->context);
    }

    public function testASynchronousPayoutClosesTheReturn(): void
    {
        $repository = $this->returnRepository();

        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->once())->method('refund')->with('return-id');
        $stateHandler->expects($this->once())->method('finish')->with('return-id');

        $this->service(
            $this->paymentRefundService($this->refund('RF-1', new \DateTimeImmutable())),
            $stateHandler,
            $repository
        )->refund($this->orderReturn(), $this->order(), $this->context);

        static::assertSame([[['id' => 'return-id', 'paymentRefundNo' => 'RF-1']]], $repository->updates);
    }

    public function testAnAsynchronousPayoutLeavesTheReturnInRefunding(): void
    {
        $repository = $this->returnRepository();

        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->once())->method('refund');
        $stateHandler->expects($this->never())->method('finish');

        $this->service($this->paymentRefundService($this->refund('RF-1', null)), $stateHandler, $repository)
            ->refund($this->orderReturn(), $this->order(), $this->context);

        // the number is written before the state moves, so the subscriber that closes the return when
        // the channel confirms finds it by exactly this number
        static::assertSame([[['id' => 'return-id', 'paymentRefundNo' => 'RF-1']]], $repository->updates);
    }

    /**
     * A source that holds 500 of the 1999 leaves 1499 for the gateway - which only collected 1000 and
     * therefore refuses. The exception carries the requested amount, so it pins the split: the gateway
     * must never be asked for money it did not collect.
     */
    public function testTheSourcesPayTheirPartBeforeTheGatewayIsAskedForTheRest(): void
    {
        $source = $this->source(refundable: 500);

        try {
            $this->service(sources: [$source])->refund($this->orderReturn(), $this->order(), $this->context);
            static::fail('the gateway has to refuse an amount it never collected');
        } catch (PaymentException $exception) {
            static::assertSame(
                PaymentOrderException::refundAmountExceedsOrder('PG-1', 1499, 1000)->getMessage(),
                $exception->getMessage()
            );
        }

        static::assertSame([500], $source->refunded);
    }

    public function testASourceThatCoversTheWholeReturnKeepsTheGatewayOut(): void
    {
        $source = $this->source(refundable: 1999);
        $repository = $this->returnRepository();

        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->once())->method('refund')->with('return-id');
        $stateHandler->expects($this->once())->method('finish')->with('return-id');

        $this->service(paymentRefundService: $this->paymentRefundService(null), stateHandler: $stateHandler, repository: $repository, sources: [$source])
            ->refund($this->orderReturn(), $this->order(), $this->context);

        static::assertSame([1999], $source->refunded);
        // no gateway refund was started, so the return carries no refund number of one
        static::assertSame([], $repository->updates);
    }

    public function testASourceIsNeverAskedForMoreThanTheReturnIsWorth(): void
    {
        $source = $this->source(refundable: 5000);

        $this->service(sources: [$source])->refund($this->orderReturn(), $this->order(), $this->context);

        static::assertSame([1999], $source->refunded);
    }

    public function testWalletRedirectionPreservesPointsAndCombinesCashInOneBooking(): void
    {
        $orderReturn = $this->orderReturn();
        $orderReturn->refundDestination = 'wallet';
        $orderReturn->refundAmount = 1000;
        $wallet = new RecordingWalletDestination(200, 1000);
        $points = $this->source(100);
        $this->service(sources: [$wallet, $points])->refund($orderReturn, $this->order(), $this->context);

        static::assertSame([900], $wallet->amounts);
        static::assertSame([100], $points->refunded);
    }

    public function testWalletDestinationNeverFallsBackToTheGateway(): void
    {
        $orderReturn = $this->orderReturn();
        $orderReturn->assign(['refundDestination' => 'wallet']);
        $gateway = $this->paymentRefundService($this->refund('RF-replayed', new \DateTimeImmutable()));
        $source = $this->source(0);
        $this->expectException(OrderReturnException::class);

        $this->service(paymentRefundService: $gateway, sources: [$source])->refund($orderReturn, $this->order(), $this->context);
    }

    /**
     * @param list<PaymentAppEntity>|null $apps null means "the app of the payment exists"
     * @param StaticEntityRepository<OrderReturnCollection>|null $repository
     * @param list<OrderReturnRefundSource> $sources
     */
    private function service(
        ?PaymentRefundService $paymentRefundService = null,
        ?OrderReturnStateHandler $stateHandler = null,
        ?StaticEntityRepository $repository = null,
        ?PaymentOrderEntity $paymentOrder = null,
        ?array $apps = null,
        array $sources = [],
    ): OrderReturnRefundService {
        $paymentApps = new PaymentAppCollection();

        foreach ($apps ?? [$this->paymentApp()] as $app) {
            $app->setUniqueIdentifier($app->id);
            $paymentApps->add($app);
        }

        $executor = static::createStub(OrderReturnRefundExecutor::class);
        $executor->method('run')->willReturnCallback(static fn (OrderReturnEntity $orderReturn, OrderEntity $order, \Closure $write) => $write());

        return new OrderReturnRefundService(
            new StaticEntityRepository([$paymentApps]),
            $repository ?? $this->returnRepository(),
            $this->paymentOrderService($paymentOrder ?? $this->paymentOrder()),
            $paymentRefundService ?? $this->paymentRefundService(null),
            $stateHandler ?? static::createStub(OrderReturnStateHandler::class),
            $executor,
            $sources,
        );
    }

    /**
     * A refund source that holds a fixed amount and records what it was asked to pay out.
     */
    private function source(int $refundable): RecordingRefundSource
    {
        return new RecordingRefundSource($refundable);
    }

    private function paymentApp(): PaymentAppEntity
    {
        return (new PaymentAppEntity())->assign(['id' => 'payment-app-id']);
    }

    private function paymentOrderService(?PaymentOrderEntity $paymentOrder): PaymentOrderService
    {
        $paymentOrders = new PaymentOrderCollection();

        if ($paymentOrder !== null) {
            $paymentOrder->setUniqueIdentifier($paymentOrder->id);
            $paymentOrders->add($paymentOrder);
        }

        $repository = new StaticEntityRepository([$paymentOrders]);

        return PaymentGatewayFixture::paymentOrderService($repository);
    }

    /**
     * The replay of an out refund number is answered before the gateway is called, so the refund that
     * is replayed is the one the service under test gets back. The gateway services are built by the
     * fixture of the payment module: they are `final`, and building them for real is what the payment
     * tests do as well.
     */
    private function paymentRefundService(?PaymentRefundEntity $replayed): PaymentRefundService
    {
        $refunds = new PaymentRefundCollection();

        if ($replayed !== null) {
            $replayed->setUniqueIdentifier($replayed->id);
            $refunds->add($replayed);
        }

        $repository = new StaticEntityRepository([$refunds]);

        return PaymentGatewayFixture::refundService($repository, $this->paymentOrderService($this->paymentOrder()));
    }

    /**
     * @return StaticEntityRepository<OrderReturnCollection>
     */
    private function returnRepository(): StaticEntityRepository
    {
        return new StaticEntityRepository([new OrderReturnCollection()]);
    }

    private function orderReturn(
        int $refundAmount = 1999,
        ?string $paymentRefundNo = null,
    ): OrderReturnEntity {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => 'order-id',
            'refundAmount' => $refundAmount,
            'currency' => 'CNY',
            'paymentRefundNo' => $paymentRefundNo,
        ]);
    }

    private function order(?string $transactionId = 'transaction-id'): OrderEntity
    {
        return (new OrderEntity())->assign([
            'id' => 'order-id',
            'primaryOrderTransactionId' => $transactionId,
        ]);
    }

    private function paymentOrder(bool $paid = true): PaymentOrderEntity
    {
        return (new PaymentOrderEntity())->assign([
            'id' => 'payment-order-id',
            'paymentNo' => 'PG-1',
            'paymentAppId' => 'payment-app-id',
            'paidAt' => $paid ? new \DateTimeImmutable('2026-10-01 10:00:00') : null,
            // what the channel really collected: less than the order, because the wallet paid part of it
            'paidAmount' => 1000,
            'refundedAmount' => 0,
        ]);
    }

    private function refund(string $refundNo, ?\DateTimeInterface $refundedAt): PaymentRefundEntity
    {
        return (new PaymentRefundEntity())->assign([
            'id' => 'refund-id',
            'refundNo' => $refundNo,
            'outRefundNo' => 'RT-1',
            'amount' => 1999,
            'reason' => 'Return RT-1',
            'refundedAt' => $refundedAt,
        ]);
    }
}

/**
 * A refund source that holds a fixed amount and records what it was asked to pay out.
 *
 * @internal
 */
#[Package('after-sales')]
final class RecordingRefundSource implements OrderReturnRefundSource
{
    /**
     * @var list<int>
     */
    public array $refunded = [];

    public function __construct(private readonly int $refundable)
    {
    }

    public function refundableAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        return $this->refundable;
    }

    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, int $amount, Context $context): void
    {
        $this->refunded[] = $amount;
    }
}

/**
 * @internal
 */
final class RecordingWalletDestination implements OrderReturnRefundDestination
{
    /**
     * @var list<int>
     */
    public array $amounts = [];

    public function __construct(private readonly int $originalAmount, private readonly int $capacity)
    {
    }

    public function getDestination(): string
    {
        return 'wallet';
    }

    public function refundableAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        return $this->originalAmount;
    }

    public function refundableDestinationAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        return $this->capacity;
    }

    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, int $amount, Context $context): void
    {
        $this->amounts[] = $amount;
    }
}
