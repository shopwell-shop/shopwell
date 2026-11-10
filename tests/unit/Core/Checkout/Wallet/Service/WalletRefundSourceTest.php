<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Wallet\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Checkout\Wallet\Service\WalletPaymentService;
use Shopwell\Core\Checkout\Wallet\Service\WalletRefundSource;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * The wallet answers two questions for a return: how much of the order it still holds, and how to give
 * that back. Both are read from the ledger, never from the credit line item of the order - the debit
 * is capped to what the wallet really held, so only a booked amount can be paid back.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(WalletRefundSource::class)]
class WalletRefundSourceTest extends TestCase
{
    private const ORDER_ID = 'order-id';

    private const CUSTOMER_ID = 'customer-id';

    private const CURRENCY_ID = 'currency-id';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testAGuestReturnNeverTouchedAWallet(): void
    {
        $payments = $this->createMock(WalletPaymentService::class);
        $payments->expects($this->never())->method('refundForOrder');

        $source = new WalletRefundSource($this->repository([]), $payments);
        $orderReturn = $this->orderReturn(customerId: null);

        static::assertSame(0, $source->refundableAmount($orderReturn, $this->order(), $this->context));

        $source->refund($orderReturn, $this->order(), 1000, $this->context);
    }

    public function testRefundableIsWhatTheOrderTookMinusWhatWasAlreadyGivenBack(): void
    {
        $repository = $this->repository([
            $this->transaction('consume', -100.0),
            $this->transaction('refund', 30.0),
        ]);

        $source = new WalletRefundSource($repository, static::createStub(WalletPaymentService::class));

        static::assertSame(7000, $source->refundableAmount($this->orderReturn(), $this->order(), $this->context));
    }

    public function testAReturnOfAnUnpaidWalletGetsNothing(): void
    {
        $source = new WalletRefundSource($this->repository([]), static::createStub(WalletPaymentService::class));

        static::assertSame(0, $source->refundableAmount($this->orderReturn(), $this->order(), $this->context));
    }

    public function testARefundIsBookedForTheReturnNumber(): void
    {
        $payments = $this->createMock(WalletPaymentService::class);
        $payments->expects($this->once())
            ->method('refundForOrder')
            ->with(
                self::ORDER_ID,
                self::CUSTOMER_ID,
                self::CURRENCY_ID,
                70.0,
                $this->context,
                'RT-1'
            );

        (new WalletRefundSource($this->repository([]), $payments))
            ->refund($this->orderReturn(), $this->order(), 7000, $this->context);
    }

    public function testOperatorCanRedirectCollectedGatewayMoneyToWallet(): void
    {
        $paymentOrders = static::createStub(PaymentOrderService::class);
        $paymentOrders->method('findLatestByOrderTransaction')->willReturn((new PaymentOrderEntity())->assign([
            'id' => 'payment-id', 'paidAt' => new \DateTimeImmutable(), 'paidAmount' => 10000, 'refundedAmount' => 3000,
        ]));
        $order = $this->order();
        $order->setPrimaryOrderTransactionId('transaction-id');
        $orderReturn = $this->orderReturn();
        $orderReturn->assign(['refundDestination' => 'wallet']);
        $source = new WalletRefundSource($this->repository([
            $this->transaction('consume', -20.0), $this->transaction('refund', 30.0),
        ]), static::createStub(WalletPaymentService::class), $paymentOrders);

        static::assertSame(7000, $source->refundableDestinationAmount($orderReturn, $order, $this->context));
    }

    public function testReplayIncludesTheSameReturnWithoutCreditingItTwice(): void
    {
        $refund = $this->transaction('refund', 70.0);
        $refund['externalReference'] = 'RT-1';
        $source = new WalletRefundSource($this->repository([
            $this->transaction('consume', -100.0), $refund,
        ]), static::createStub(WalletPaymentService::class));

        static::assertSame(10000, $source->refundableAmount($this->orderReturn(), $this->order(), $this->context));
    }

    public function testWalletPayoutAlsoBooksOnlyTheRedirectedGatewayPart(): void
    {
        $payment = (new PaymentOrderEntity())->assign(['id' => 'payment-id', 'paidAt' => new \DateTimeImmutable(), 'paidAmount' => 10000]);
        $paymentOrders = $this->createMock(PaymentOrderService::class);
        $paymentOrders->method('findLatestByOrderTransaction')->willReturn($payment);
        $paymentOrders->expects($this->once())->method('applyRefund')->with($payment, 1000)->willReturn($payment);
        $lock = static::createStub(PaymentOrderRowLock::class);
        $lock->method('transaction')->willReturnCallback(static fn (\Closure $write) => $write());
        $lock->method('lockOrder')->willReturn(['paidAmount' => 10000, 'refundedAmount' => 0]);
        $lock->method('reservedRefundAmount')->willReturn(0);
        $wallet = $this->createMock(WalletPaymentService::class);
        $wallet->expects($this->once())->method('refundForOrder')->with(self::ORDER_ID, self::CUSTOMER_ID, self::CURRENCY_ID, 30.0, $this->context, 'RT-1');
        $source = new WalletRefundSource($this->repository([$this->transaction('consume', -20.0)]), $wallet, $paymentOrders, $lock);
        $order = $this->order();
        $order->setPrimaryOrderTransactionId('transaction-id');
        $orderReturn = $this->orderReturn();
        $orderReturn->refundDestination = 'wallet';

        $source->refund($orderReturn, $order, 3000, $this->context);
    }

    public function testReservedGatewayFundsCannotBeCreditedToTheWallet(): void
    {
        $payment = (new PaymentOrderEntity())->assign(['id' => 'payment-id', 'paidAt' => new \DateTimeImmutable(), 'paidAmount' => 8000]);
        $paymentOrders = $this->createMock(PaymentOrderService::class);
        $paymentOrders->method('findLatestByOrderTransaction')->willReturn($payment);
        $paymentOrders->expects($this->never())->method('applyRefund');
        $lock = static::createStub(PaymentOrderRowLock::class);
        $lock->method('transaction')->willReturnCallback(static fn (\Closure $write) => $write());
        $lock->method('lockOrder')->willReturn(['paidAmount' => 8000, 'refundedAmount' => 0]);
        $lock->method('reservedRefundAmount')->willReturn(1000);
        $wallet = $this->createMock(WalletPaymentService::class);
        $wallet->expects($this->never())->method('refundForOrder');
        $source = new WalletRefundSource($this->repository([]), $wallet, $paymentOrders, $lock);
        $order = $this->order();
        $order->setPrimaryOrderTransactionId('transaction-id');
        $orderReturn = $this->orderReturn();
        $orderReturn->refundDestination = 'wallet';

        static::assertSame(7000, $source->refundableDestinationAmount($orderReturn, $order, $this->context));
        $this->expectExceptionObject(WalletException::refundNotPossible('RT-1', 'the collected payment has insufficient refundable funds.'));
        $source->refund($orderReturn, $order, 8000, $this->context);
    }

    public function testAReplayedGatewayRedirectionDoesNotBookThePaymentTwice(): void
    {
        $payment = (new PaymentOrderEntity())->assign(['id' => 'payment-id', 'paidAt' => new \DateTimeImmutable(), 'paidAmount' => 10000]);
        $paymentOrders = $this->createMock(PaymentOrderService::class);
        $paymentOrders->method('findLatestByOrderTransaction')->willReturn($payment);
        $paymentOrders->expects($this->never())->method('applyRefund');
        $lock = static::createStub(PaymentOrderRowLock::class);
        $lock->method('transaction')->willReturnCallback(static fn (\Closure $write) => $write());
        $lock->method('lockOrder')->willReturn(['paidAmount' => 10000, 'refundedAmount' => 3000]);
        $wallet = $this->createMock(WalletPaymentService::class);
        $wallet->expects($this->never())->method('refundForOrder');
        $refund = $this->transaction('refund', 30.0);
        $refund['externalReference'] = 'RT-1';
        $source = new WalletRefundSource($this->repository([$refund]), $wallet, $paymentOrders, $lock);
        $order = $this->order();
        $order->setPrimaryOrderTransactionId('transaction-id');
        $orderReturn = $this->orderReturn();
        $orderReturn->refundDestination = 'wallet';

        static::assertSame(3000, $source->refundableDestinationAmount($orderReturn, $order, $this->context));
        $source->refund($orderReturn, $order, 3000, $this->context);
    }

    /**
     * The stub answers one scripted call per `search()`, and a refundable amount costs two of them -
     * the debit and the credit of the order - so the same script is queued twice.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return StaticEntityRepository<CustomerWalletTransactionCollection>
     */
    private function repository(array $rows): StaticEntityRepository
    {
        $search = static function (Criteria $criteria) use ($rows): CustomerWalletTransactionCollection {
            $collection = new CustomerWalletTransactionCollection();

            foreach ($rows as $row) {
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof EqualsFilter && ($row[$filter->getField()] ?? null) !== $filter->getValue()) {
                        continue 2;
                    }
                }

                $collection->add((new CustomerWalletTransactionEntity())->assign($row));
            }

            return $collection;
        };

        return new StaticEntityRepository(array_fill(0, 10, $search));
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(string $type, float $amount): array
    {
        return [
            'id' => Uuid::randomHex(),
            'orderId' => self::ORDER_ID,
            'type' => $type,
            'amount' => $amount,
        ];
    }

    private function orderReturn(?string $customerId = self::CUSTOMER_ID): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => self::ORDER_ID,
            'customerId' => $customerId,
            'currency' => 'CNY',
            'refundAmount' => 7000,
        ]);
    }

    private function order(): OrderEntity
    {
        return (new OrderEntity())->assign([
            'id' => self::ORDER_ID,
            'currencyId' => self::CURRENCY_ID,
        ]);
    }
}
