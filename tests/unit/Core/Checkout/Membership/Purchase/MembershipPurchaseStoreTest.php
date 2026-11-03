<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Purchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseEntity;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipPurchase;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipPurchaseStore;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipPurchaseStore::class)]
class MembershipPurchaseStoreTest extends TestCase
{
    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<MembershipPurchaseCollection>
     */
    private StaticEntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->orderRepository = StaticEntityRepository::of(MembershipPurchaseCollection::class);
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
            'state' => MembershipPurchaseEntity::STATE_OPEN,
        ];

        $rows = $this->createMock(RowLock::class);
        $rows->expects($this->once())
            ->method('lock')
            ->with('membership_purchase', ['id' => self::ORDER_ID])
            ->willReturn($row);

        $purchase = $this->store($rows)->lockForSettlement(self::ORDER_ID);

        static::assertInstanceOf(MembershipPurchase::class, $purchase);
        static::assertSame(self::ORDER_ID, $purchase->purchaseId());
    }

    public function testAnUnknownPaymentAnswersNothing(): void
    {
        $rows = static::createStub(RowLock::class);
        $rows->method('lock')->willReturn(null);

        static::assertNull($this->store($rows)->lockForSettlement(self::ORDER_ID));
    }

    private function store(RowLock $rows): MembershipPurchaseStore
    {
        return new MembershipPurchaseStore(
            $this->orderRepository,
            static::createStub(MembershipSubscriptionService::class),
            $rows,
        );
    }
}
