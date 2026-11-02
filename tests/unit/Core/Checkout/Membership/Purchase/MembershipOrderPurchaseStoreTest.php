<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Purchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipOrderPurchase;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipOrderPurchaseStore;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipOrderPurchaseStore::class)]
class MembershipOrderPurchaseStoreTest extends TestCase
{
    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<MembershipOrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->orderRepository = StaticEntityRepository::of(MembershipOrderCollection::class);
    }

    public function testItLocksTheOrderOfThePayment(): void
    {
        $row = [
            'id' => self::ORDER_ID,
            'customer_id' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90',
            'loyalty_tier_id' => 'd1b2c3d4e5f60718293a4b5c6d7e8f90',
            'duration_days' => 365,
            'amount' => 199.0,
            'currency' => 'CNY',
            'state' => MembershipOrderEntity::STATE_OPEN,
        ];

        $rows = $this->createMock(RowLock::class);
        $rows->expects($this->once())
            ->method('lock')
            ->with('membership_order', ['id' => self::ORDER_ID])
            ->willReturn($row);

        $purchase = $this->store($rows)->lockForSettlement(self::ORDER_ID);

        static::assertInstanceOf(MembershipOrderPurchase::class, $purchase);
        static::assertSame(self::ORDER_ID, $purchase->purchaseId());
    }

    public function testAnUnknownPaymentAnswersNothing(): void
    {
        $rows = static::createStub(RowLock::class);
        $rows->method('lock')->willReturn(null);

        static::assertNull($this->store($rows)->lockForSettlement(self::ORDER_ID));
    }

    private function store(RowLock $rows): MembershipOrderPurchaseStore
    {
        return new MembershipOrderPurchaseStore(
            $this->orderRepository,
            static::createStub(MembershipSubscriptionService::class),
            $rows,
        );
    }
}
