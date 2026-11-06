<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Loyalty\Cart\LoyaltyPointCartProcessor;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionEntity;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyReturnPoints;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * A return undoes both halves of what an order did to the points: the discount it took when the
 * customer paid with points, and the grant it made when the goods were bought.
 *
 * The numbers below are the whole point of the class - 600 points bought 6.00 of discount on an order
 * worth 300.00 - so the tests work with that rate instead of the ladder, which may have changed since.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyReturnPoints::class)]
class LoyaltyReturnPointsTest extends TestCase
{
    private const ORDER_ID = 'order-id';

    private const CUSTOMER_ID = 'customer-id';

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testAnOrderThatNeverSpentPointsSettlesNothing(): void
    {
        $lots = $this->createMock(LoyaltyPointLots::class);
        $lots->expects($this->never())->method('usageOf');

        $points = $this->points($lots, $this->ledger(), $this->repository());

        static::assertSame(0, $points->refundableValue($this->orderReturn(), $this->order(withRedemption: false), $this->context));
    }

    public function testTheRefundableValueIsWhatIsStillOut(): void
    {
        $points = $this->points($this->lots(taken: 600, outstanding: 300), $this->ledger(), $this->repository());

        // 600 points bought 6.00 of discount, so the 300 points still out are worth 3.00
        static::assertSame(300, $points->refundableValue($this->orderReturn(), $this->order(), $this->context));
    }

    public function testEverythingCameBackAlreadySoThereIsNothingLeftToSettle(): void
    {
        $points = $this->points($this->lots(taken: 600, outstanding: 0), $this->ledger(), $this->repository());

        static::assertSame(0, $points->refundableValue($this->orderReturn(), $this->order(), $this->context));
    }

    public function testGivingBackConvertsTheValueBackIntoPoints(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->once())
            ->method('giveBackPoints')
            ->with(self::CUSTOMER_ID, 'return', self::ORDER_ID, 600, $this->context, 'RT-1')
            ->willReturn(600);

        $this->points($this->lots(), $ledger, $this->repository())
            ->giveBack($this->orderReturn(), $this->order(), 600, $this->context);
    }

    /**
     * A part return settles its share: 300 of the 6.00 discount are 300 points, not all 600.
     */
    public function testAPartReturnGivesBackItsShare(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->once())
            ->method('giveBackPoints')
            ->with(self::CUSTOMER_ID, 'return', self::ORDER_ID, 300, $this->context, 'RT-1')
            ->willReturn(300);

        $this->points($this->lots(taken: 600, outstanding: 300), $ledger, $this->repository())
            ->giveBack($this->orderReturn(), $this->order(), 300, $this->context);
    }

    public function testAGuestReturnGivesNothingBack(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->never())->method('giveBackPoints');

        $this->points($this->lots(), $ledger, $this->repository())
            ->giveBack($this->orderReturn(customerId: null), $this->order(), 600, $this->context);
    }

    /**
     * Half of the goods came back, so half of the 900 points the order earned go with them.
     */
    public function testRevokingTakesTheShareOfTheGoodsThatCameBack(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->once())
            ->method('revokeOrderPoints')
            ->with(self::CUSTOMER_ID, 'revoke', self::ORDER_ID, 450, $this->context, 'RT-1')
            ->willReturn(450);

        $this->points($this->lots(), $ledger, $this->repository([$this->booking('award', 900, self::ORDER_ID)]))
            ->revokeEarned($this->orderReturn(refundAmount: 15000), $this->order(), $this->context);
    }

    public function testAFullReturnTakesTheWholeGrant(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->once())
            ->method('revokeOrderPoints')
            ->with(self::CUSTOMER_ID, 'revoke', self::ORDER_ID, 900, $this->context, 'RT-1')
            ->willReturn(900);

        $this->points($this->lots(), $ledger, $this->repository([$this->booking('award', 900, self::ORDER_ID)]))
            ->revokeEarned($this->orderReturn(refundAmount: 30000), $this->order(), $this->context);
    }

    public function testRevokingIsSilentWhenTheOrderEarnedNothing(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->never())->method('revokeOrderPoints');

        $this->points($this->lots(), $ledger, $this->repository())
            ->revokeEarned($this->orderReturn(), $this->order(), $this->context);
    }

    /**
     * A guest return has no account to take points from, and the grant of a guest order is nobody's.
     */
    public function testAGuestReturnRevokesNothing(): void
    {
        $ledger = $this->createMock(LoyaltyLedger::class);
        $ledger->expects($this->never())->method('revokeOrderPoints');

        $this->points($this->lots(), $ledger, $this->repository([$this->booking('award', 900, self::ORDER_ID)]))
            ->revokeEarned($this->orderReturn(customerId: null), $this->order(), $this->context);
    }

    /**
     * @param StaticEntityRepository<LoyaltyPointTransactionCollection> $repository
     */
    private function points(LoyaltyPointLots $lots, LoyaltyLedger $ledger, StaticEntityRepository $repository): LoyaltyReturnPoints
    {
        return new LoyaltyReturnPoints($repository, $lots, $ledger);
    }

    private function lots(int $taken = 600, int $outstanding = 600): LoyaltyPointLots
    {
        $lots = static::createStub(LoyaltyPointLots::class);
        $lots->method('usageOf')->willReturn(['taken' => $taken, 'outstanding' => $outstanding]);

        return $lots;
    }

    private function ledger(): LoyaltyLedger
    {
        return static::createStub(LoyaltyLedger::class);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return StaticEntityRepository<LoyaltyPointTransactionCollection>
     */
    private function repository(array $rows = []): StaticEntityRepository
    {
        $search = static function (Criteria $criteria) use ($rows): LoyaltyPointTransactionCollection {
            $collection = new LoyaltyPointTransactionCollection();

            foreach ($rows as $row) {
                foreach ($criteria->getFilters() as $filter) {
                    if ($filter instanceof EqualsFilter && ($row[$filter->getField()] ?? null) !== $filter->getValue()) {
                        continue 2;
                    }
                }

                $collection->add((new LoyaltyPointTransactionEntity())->assign($row));
            }

            return $collection;
        };

        return new StaticEntityRepository([$search]);
    }

    /**
     * @return array<string, mixed>
     */
    private function booking(string $type, int $points, string $orderId): array
    {
        return [
            'id' => Uuid::randomHex(),
            'orderId' => $orderId,
            'type' => $type,
            'points' => $points,
        ];
    }

    /**
     * An order of two goods worth 300.00 in total, paid with 600 points (6.00 of discount) unless the
     * test says otherwise.
     */
    private function order(bool $withRedemption = true): OrderEntity
    {
        $lineItems = new OrderLineItemCollection([
            $this->productLineItem('goods-a', 100.0),
            $this->productLineItem('goods-b', 200.0),
        ]);

        if ($withRedemption) {
            $lineItems->add($this->redemptionLineItem());
        }

        return (new OrderEntity())->assign([
            'id' => self::ORDER_ID,
            'lineItems' => $lineItems,
        ]);
    }

    private function productLineItem(string $id, float $unitPrice): OrderLineItemEntity
    {
        return (new OrderLineItemEntity())->assign([
            'id' => $id,
            'identifier' => $id,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'unitPrice' => $unitPrice,
            'quantity' => 1,
            'price' => new CalculatedPrice($unitPrice, $unitPrice, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
    }

    private function redemptionLineItem(): OrderLineItemEntity
    {
        return (new OrderLineItemEntity())->assign([
            'id' => LoyaltyPointCartProcessor::LINE_ITEM_ID,
            'identifier' => LoyaltyPointCartProcessor::LINE_ITEM_ID,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'unitPrice' => -6.0,
            'quantity' => 1,
            'payload' => ['points' => 600],
            'price' => new CalculatedPrice(-6.0, -6.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]);
    }

    private function orderReturn(?string $customerId = self::CUSTOMER_ID, int $refundAmount = 15000): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'orderId' => self::ORDER_ID,
            'customerId' => $customerId,
            'currency' => 'CNY',
            'refundAmount' => $refundAmount,
        ]);
    }
}
