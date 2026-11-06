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
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
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

        return new StaticEntityRepository([$search, $search]);
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
