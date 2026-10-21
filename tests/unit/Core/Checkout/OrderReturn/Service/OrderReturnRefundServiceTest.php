<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundService;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderCollection;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundCollection;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundService;
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
     * @param list<PaymentAppEntity>|null $apps null means "the app of the payment exists"
     * @param StaticEntityRepository<OrderReturnCollection>|null $repository
     */
    private function service(
        ?PaymentRefundService $refundService = null,
        ?OrderReturnStateHandler $stateHandler = null,
        ?StaticEntityRepository $repository = null,
        ?PaymentOrderEntity $paymentOrder = null,
        ?array $apps = null,
    ): OrderReturnRefundService {
        $paymentApps = new PaymentAppCollection();

        foreach ($apps ?? [$this->paymentApp()] as $app) {
            $app->setUniqueIdentifier($app->id);
            $paymentApps->add($app);
        }

        return new OrderReturnRefundService(
            new StaticEntityRepository([$paymentApps]),
            $repository ?? $this->returnRepository(),
            $this->paymentOrderService($paymentOrder ?? $this->paymentOrder()),
            $refundService ?? $this->paymentRefundService(null),
            $stateHandler ?? static::createStub(OrderReturnStateHandler::class),
        );
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

        /** @var StaticEntityRepository<PaymentOrderCollection> $repository */
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

        /** @var StaticEntityRepository<PaymentRefundCollection> $repository */
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
            'paymentAppId' => 'payment-app-id',
            'paidAt' => $paid ? new \DateTimeImmutable('2026-10-01 10:00:00') : null,
        ]);
    }

    private function refund(string $refundNo, ?\DateTimeInterface $refundedAt): PaymentRefundEntity
    {
        return (new PaymentRefundEntity())->assign([
            'id' => 'refund-id',
            'refundNo' => $refundNo,
            'outRefundNo' => 'RT-1',
            'refundedAt' => $refundedAt,
        ]);
    }
}
