<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Purchase;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionEntity;
use Shopwell\Core\Checkout\Membership\MembershipSubscriptionStates;
use Shopwell\Core\Checkout\Membership\Purchase\MembershipOrderPurchase;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * The purchase does not decide what a membership is - it hands the order to the membership service and
 * records on the order which period that purchase bought.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipOrderPurchase::class)]
class MembershipOrderPurchaseTest extends TestCase
{
    private const ORDER_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<MembershipOrderCollection>
     */
    private StaticEntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = StaticEntityRepository::of(MembershipOrderCollection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testItAnswersWhatItWasReadFor(): void
    {
        $purchase = $this->purchase();

        static::assertSame(self::ORDER_ID, $purchase->purchaseId());
        static::assertSame(19900, $purchase->amountMinor());
        static::assertFalse($purchase->isSettled());
        static::assertTrue($purchase->isSettleable());
    }

    public function testAnActivatedOrderIsAlreadySettled(): void
    {
        static::assertTrue($this->purchase(state: MembershipOrderEntity::STATE_ACTIVATED)->isSettled());
    }

    /**
     * A tier that was deleted while the payment was open cannot be granted, so the order has to fail
     * instead of settling into nothing.
     */
    public function testAnOrderWhoseTierIsGoneIsNotSettleable(): void
    {
        static::assertFalse($this->purchase(tierId: null)->isSettleable());
    }

    public function testSettleOpensTheMembershipAndRecordsThePeriodItBought(): void
    {
        $settledAt = new \DateTimeImmutable('2026-06-01 12:00:00');
        $endsAt = new \DateTimeImmutable('2027-06-01 12:00:00');

        $subscriptions = $this->createMock(MembershipSubscriptionService::class);
        $subscriptions->expects($this->once())
            ->method('open')
            ->with(
                static::callback(static fn (array $row): bool => $row['id'] === self::ORDER_ID),
                $this->context
            )
            ->willReturn($this->subscription($endsAt));

        $this->purchase(subscriptions: $subscriptions)->settle('PAY-1', $settledAt, $this->context);

        $update = $this->orderRepository->updates[0][0];
        static::assertSame(self::ORDER_ID, $update['id']);
        static::assertSame(MembershipOrderEntity::STATE_ACTIVATED, $update['state']);
        static::assertSame('PAY-1', $update['paymentNo']);
        static::assertSame($settledAt, $update['paidAt']);
        static::assertSame($settledAt, $update['activatedAt']);

        // the period of this order is read back from the end of the membership, because a renewal
        // extends a membership that may have started long before this purchase
        static::assertEquals(new \DateTimeImmutable('2026-06-01 12:00:00'), $update['startsAt']);
        static::assertSame($endsAt, $update['expiresAt']);
    }

    public function testFailRecordsTheAttemptWithoutGrantingAnything(): void
    {
        $failedAt = new \DateTimeImmutable('2026-06-01 12:00:00');

        $subscriptions = $this->createMock(MembershipSubscriptionService::class);
        $subscriptions->expects($this->never())->method('open');

        $this->purchase(subscriptions: $subscriptions)->fail('PAY-1', $failedAt, $this->context);

        $update = $this->orderRepository->updates[0][0];
        static::assertSame(MembershipOrderEntity::STATE_FAILED, $update['state']);
        static::assertSame('PAY-1', $update['paymentNo']);
        static::assertSame($failedAt, $update['paidAt']);
    }

    private function subscription(\DateTimeInterface $endsAt): MembershipSubscriptionEntity
    {
        return (new MembershipSubscriptionEntity())->assign([
            'id' => 'a1b2c3d4e5f60718293a4b5c6d7e8f91',
            'customerId' => self::CUSTOMER_ID,
            'loyaltyTierId' => self::TIER_ID,
            'state' => MembershipSubscriptionStates::STATE_ACTIVE,
            'startsAt' => new \DateTimeImmutable('2026-06-01 12:00:00'),
            'endsAt' => $endsAt,
        ]);
    }

    private function purchase(
        string $state = MembershipOrderEntity::STATE_OPEN,
        ?string $tierId = self::TIER_ID,
        ?MembershipSubscriptionService $subscriptions = null,
    ): MembershipOrderPurchase {
        return new MembershipOrderPurchase(
            [
                'id' => self::ORDER_ID,
                'customer_id' => self::CUSTOMER_ID,
                'membership_plan_id' => null,
                'loyalty_tier_id' => $tierId,
                'plan_name' => 'Gold',
                'duration_days' => 365,
                'amount' => 199.0,
                'currency' => 'CNY',
                'state' => $state,
            ],
            $this->orderRepository,
            $subscriptions ?? static::createStub(MembershipSubscriptionService::class),
        );
    }
}
