<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Membership\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyAccountService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionDefinition;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionEntity;
use Shopwell\Core\Checkout\Membership\MembershipException;
use Shopwell\Core\Checkout\Membership\MembershipSubscriptionStates;
use Shopwell\Core\Checkout\Membership\Service\MembershipSubscriptionService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * The service owns three decisions and they are what these tests pin: which membership a purchase
 * belongs to, how that membership ends, and that the loyalty account is only ever a projection of it.
 *
 * The database is not involved: the connection double answers by the key it is asked for, so the
 * queries of the row lock and the one lookup of the running membership stay distinguishable.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MembershipSubscriptionService::class)]
class MembershipSubscriptionServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ACCOUNT_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ORDER_ID = 'd1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const SUBSCRIPTION_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'f1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const OTHER_TIER_ID = 'f1b2c3d4e5f60718293a4b5c6d7e8f91';

    private const NOW = '2026-06-01 12:00:00';

    private const RUNNING_UNTIL = '2026-12-01 00:00:00';

    /**
     * @var StaticEntityRepository<MembershipSubscriptionCollection>
     */
    private StaticEntityRepository $subscriptionRepository;

    /**
     * @var StaticEntityRepository<LoyaltyAccountCollection>
     */
    private StaticEntityRepository $loyaltyAccountRepository;

    private Connection&Stub $connection;

    private Context $context;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $runningRow = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $lockedRow = null;

    /**
     * @var list<string>
     */
    private array $dueIds = [];

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->loyaltyAccountRepository = StaticEntityRepository::of(LoyaltyAccountCollection::class);
        $this->connection = static::createStub(Connection::class);

        $this->connection->method('transactional')->willReturnCallback(
            static fn (callable $callback) => $callback()
        );

        $this->connection->method('fetchAssociative')->willReturnCallback(
            function (string $sql, array $params): mixed {
                return $this->rowFor($params);
            }
        );

        $this->connection->method('fetchFirstColumn')->willReturnCallback(fn (): array => $this->dueIds);
    }

    public function testAPaidOrderOpensAMembershipThatStartsNow(): void
    {
        $subscriptions = new StaticEntityRepository([$this->lookup()], new MembershipSubscriptionDefinition());

        $subscription = $this->service(subscriptions: $subscriptions)->open($this->order(), $this->context);

        $created = $subscriptions->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $created['customerId']);
        static::assertSame(self::TIER_ID, $created['loyaltyTierId']);
        static::assertSame(MembershipSubscriptionStates::STATE_ACTIVE, $created['state']);
        static::assertSame(self::ORDER_ID, $created['lastPurchaseId']);
        static::assertEquals(new \DateTimeImmutable(self::NOW), $created['startsAt']);
        static::assertEquals(new \DateTimeImmutable('2026-07-01 12:00:00'), $created['endsAt']);

        // the loyalty account carries the membership, it does not own it
        $projection = $this->loyaltyAccountRepository->updates[0][0];
        static::assertSame(self::ACCOUNT_ID, $projection['id']);
        static::assertSame(self::TIER_ID, $projection['paidTierId']);
        static::assertArrayNotHasKey('tierId', $projection);
        static::assertEquals(new \DateTimeImmutable('2026-07-01 12:00:00'), $projection['membershipPaidUntil']);

        static::assertSame($created['id'], $subscription->getId());
    }

    /**
     * A customer who renews early keeps the days that are already paid, so the running membership is
     * pushed instead of a second one being opened next to it.
     */
    public function testRenewingTheSameTierPushesTheRunningMembership(): void
    {
        $this->runningRow = $this->subscriptionRow(tierId: self::TIER_ID, endsAt: self::RUNNING_UNTIL, amount: 149.0);

        $subscriptions = new StaticEntityRepository([$this->lookup()], new MembershipSubscriptionDefinition());

        $this->service(subscriptions: $subscriptions)->open($this->order(), $this->context);

        static::assertSame([], $subscriptions->creates);

        $update = $subscriptions->updates[0][0];
        static::assertSame(self::SUBSCRIPTION_ID, $update['id']);
        static::assertEquals(new \DateTimeImmutable('2026-12-31 00:00:00'), $update['endsAt']);
        static::assertSame(self::ORDER_ID, $update['lastPurchaseId']);
        // every purchase that extended this membership adds to what it cost
        static::assertEqualsWithDelta(348.0, (float) $update['amount'], 0.001);

        static::assertEquals(
            new \DateTimeImmutable('2026-12-31 00:00:00'),
            $this->loyaltyAccountRepository->updates[0][0]['membershipPaidUntil']
        );
    }

    /**
     * A tier is not a currency: the days left on the old one are dropped, and the row records why it
     * ended instead of disappearing.
     */
    public function testBuyingAnotherTierEndsTheRunningOne(): void
    {
        $this->runningRow = $this->subscriptionRow(tierId: self::OTHER_TIER_ID, endsAt: self::RUNNING_UNTIL, amount: 149.0);

        $subscriptions = new StaticEntityRepository([$this->lookup()], new MembershipSubscriptionDefinition());

        $this->service(subscriptions: $subscriptions)->open($this->order(), $this->context);

        $end = $subscriptions->updates[0][0];
        static::assertSame(self::SUBSCRIPTION_ID, $end['id']);
        static::assertSame(MembershipSubscriptionStates::STATE_ENDED, $end['state']);
        static::assertSame(MembershipSubscriptionStates::END_SUPERSEDED, $end['endReason']);
        static::assertEquals(new \DateTimeImmutable(self::NOW), $end['endedAt']);

        $created = $subscriptions->creates[0][0];
        static::assertSame(self::TIER_ID, $created['loyaltyTierId']);
        static::assertEquals(new \DateTimeImmutable(self::NOW), $created['startsAt']);
    }

    /**
     * A membership that ran out but was never ended by the task is ended here rather than extended -
     * a customer who comes back after a break starts a new membership.
     */
    public function testARanOutMembershipIsEndedBeforeTheNewOneStarts(): void
    {
        $this->runningRow = $this->subscriptionRow(tierId: self::TIER_ID, endsAt: '2026-01-01 00:00:00', amount: 149.0);

        $subscriptions = new StaticEntityRepository([$this->lookup()], new MembershipSubscriptionDefinition());

        $this->service(subscriptions: $subscriptions)->open($this->order(), $this->context);

        static::assertSame(MembershipSubscriptionStates::END_EXPIRED, $subscriptions->updates[0][0]['endReason']);
        static::assertCount(1, $subscriptions->creates);
        static::assertEquals(new \DateTimeImmutable(self::NOW), $subscriptions->creates[0][0]['startsAt']);
    }

    public function testCurrentAnswersNothingForAMembershipThatRanOut(): void
    {
        $subscriptions = new StaticEntityRepository([
            $this->collection($this->subscriptionEntity(endsAt: '2026-01-01 00:00:00')),
        ], new MembershipSubscriptionDefinition());

        static::assertNull($this->service(subscriptions: $subscriptions)->current(self::CUSTOMER_ID, $this->context));
    }

    public function testCurrentAnswersTheRunningMembership(): void
    {
        $subscriptions = new StaticEntityRepository([
            $this->collection($this->subscriptionEntity(endsAt: self::RUNNING_UNTIL)),
        ], new MembershipSubscriptionDefinition());

        $current = $this->service(subscriptions: $subscriptions)->current(self::CUSTOMER_ID, $this->context);

        static::assertSame(self::SUBSCRIPTION_ID, $current?->getId());
    }

    /**
     * Ending a membership is what takes the tier away: the projection is cleared and the tier of the
     * tier points are resolved again, which is the path that dispatches the tier change.
     */
    public function testExpiringEndsWhatRanOutAndFallsBackToTheTierPoints(): void
    {
        $this->dueIds = [Uuid::fromHexToBytes(self::SUBSCRIPTION_ID)];
        $this->lockedRow = $this->subscriptionRow(tierId: self::TIER_ID, endsAt: '2026-05-01 00:00:00', amount: 199.0);

        $tiers = $this->createMock(TierResolver::class);
        $tiers->expects($this->once())->method('resolve')->with(self::CUSTOMER_ID, 500, $this->context);

        $ended = $this->service(tiers: $tiers)->expireDue($this->context);

        static::assertSame(1, $ended);

        $update = $this->subscriptionRepository->updates[0][0];
        static::assertSame(MembershipSubscriptionStates::STATE_ENDED, $update['state']);
        static::assertSame(MembershipSubscriptionStates::END_EXPIRED, $update['endReason']);

        $projection = $this->loyaltyAccountRepository->updates[0][0];
        static::assertNull($projection['paidTierId']);
        static::assertArrayNotHasKey('tierId', $projection);
        static::assertNull($projection['membershipPaidUntil']);
    }

    public function testAMembershipThatWasExtendedMeanwhileIsNotExpired(): void
    {
        $this->dueIds = [Uuid::fromHexToBytes(self::SUBSCRIPTION_ID)];
        // the scan saw it as due, the lock now sees a membership that runs until december
        $this->lockedRow = $this->subscriptionRow(tierId: self::TIER_ID, endsAt: self::RUNNING_UNTIL, amount: 199.0);

        $tiers = $this->createMock(TierResolver::class);
        $tiers->expects($this->never())->method('resolve');

        static::assertSame(0, $this->service(tiers: $tiers)->expireDue($this->context));
        static::assertSame([], $this->subscriptionRepository->updates);
    }

    public function testRevokingEndsTheMembershipAheadOfItsPeriod(): void
    {
        $this->lockedRow = $this->subscriptionRow(tierId: self::TIER_ID, endsAt: self::RUNNING_UNTIL, amount: 199.0);

        $tiers = $this->createMock(TierResolver::class);
        $tiers->expects($this->once())->method('resolve');

        $this->service(tiers: $tiers)->revoke(self::SUBSCRIPTION_ID, $this->context);

        $update = $this->subscriptionRepository->updates[0][0];
        static::assertSame(MembershipSubscriptionStates::END_REVOKED, $update['endReason']);
        static::assertNull($this->loyaltyAccountRepository->updates[0][0]['paidTierId']);
    }

    public function testRevokingAMembershipThatAlreadyEndedChangesNothing(): void
    {
        $this->lockedRow = array_merge(
            $this->subscriptionRow(tierId: self::TIER_ID, endsAt: self::RUNNING_UNTIL, amount: 199.0),
            ['state' => MembershipSubscriptionStates::STATE_ENDED, 'end_reason' => MembershipSubscriptionStates::END_EXPIRED]
        );

        $this->service()->revoke(self::SUBSCRIPTION_ID, $this->context);

        static::assertSame([], $this->subscriptionRepository->updates);
    }

    public function testRevokingAnUnknownMembershipFails(): void
    {
        $this->lockedRow = null;

        $this->expectExceptionObject(MembershipException::subscriptionNotFound(self::SUBSCRIPTION_ID));

        $this->service()->revoke(self::SUBSCRIPTION_ID, $this->context);
    }

    /**
     * @param StaticEntityRepository<MembershipSubscriptionCollection>|null $subscriptions
     */
    private function service(?StaticEntityRepository $subscriptions = null, ?TierResolver $tiers = null): MembershipSubscriptionService
    {
        $this->subscriptionRepository ??= new StaticEntityRepository([], new MembershipSubscriptionDefinition());
        $repository = $subscriptions ?? $this->subscriptionRepository;
        $this->subscriptionRepository = $repository;

        $accounts = static::createStub(LoyaltyAccountService::class);

        return new MembershipSubscriptionService(
            $repository,
            $this->loyaltyAccountRepository,
            $accounts,
            $tiers ?? static::createStub(TierResolver::class),
            new RowLock($this->connection),
            $this->connection,
            new MockClock(self::NOW),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function order(): array
    {
        return [
            'id' => self::ORDER_ID,
            'customer_id' => self::CUSTOMER_ID,
            'membership_plan_id' => null,
            'loyalty_tier_id' => self::TIER_ID,
            'plan_name' => 'Gold',
            'amount' => 199.0,
            'currency' => 'CNY',
            'duration_days' => 30,
        ];
    }

    private function lookup(): \Closure
    {
        return function (Criteria $criteria): MembershipSubscriptionCollection {
            $written = array_merge(
                $this->subscriptionRepository->creates[0][0] ?? [],
                $this->subscriptionRepository->updates[0][0] ?? []
            );

            return $this->collection($this->subscriptionEntity(
                id: (string) ($criteria->getIds()[0] ?? self::SUBSCRIPTION_ID),
                endsAt: self::NOW,
                overrides: $written
            ));
        };
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function subscriptionEntity(
        string $id = self::SUBSCRIPTION_ID,
        string $endsAt = self::RUNNING_UNTIL,
        array $overrides = [],
    ): MembershipSubscriptionEntity {
        return (new MembershipSubscriptionEntity())->assign(array_merge([
            'id' => $id,
            'customerId' => self::CUSTOMER_ID,
            'loyaltyTierId' => self::TIER_ID,
            'state' => MembershipSubscriptionStates::STATE_ACTIVE,
            'startsAt' => new \DateTimeImmutable(self::NOW),
            'endsAt' => new \DateTimeImmutable($endsAt),
            'amount' => 199.0,
            'currency' => 'CNY',
        ], $overrides));
    }

    private function collection(MembershipSubscriptionEntity $entity): MembershipSubscriptionCollection
    {
        $collection = new MembershipSubscriptionCollection();
        $collection->add($entity);

        return $collection;
    }

    /**
     * The row as the database answers it: uuid columns in bytes, dates as strings.
     *
     * @return array<string, mixed>
     */
    private function subscriptionRow(string $tierId, string $endsAt, float $amount): array
    {
        return [
            'id' => Uuid::fromHexToBytes(self::SUBSCRIPTION_ID),
            'customer_id' => Uuid::fromHexToBytes(self::CUSTOMER_ID),
            'membership_plan_id' => null,
            'loyalty_tier_id' => Uuid::fromHexToBytes($tierId),
            'plan_name' => 'Gold',
            'amount' => $amount,
            'currency' => 'CNY',
            'state' => MembershipSubscriptionStates::STATE_ACTIVE,
            'starts_at' => '2026-01-01 00:00:00',
            'ends_at' => $endsAt,
            'ended_at' => null,
            'end_reason' => null,
            'last_purchase_id' => null,
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>|false
     */
    private function rowFor(array $params): array|false
    {
        if (isset($params['customerId'])) {
            return $this->runningRow ?? false;
        }

        if (isset($params['customer_id'])) {
            return [
                'id' => Uuid::fromHexToBytes(self::ACCOUNT_ID),
                'customer_id' => Uuid::fromHexToBytes(self::CUSTOMER_ID),
                'points_balance' => 0,
                'tier_points' => 500,
            ];
        }

        if (isset($params['id'])) {
            return $this->lockedRow ?? false;
        }

        return false;
    }
}
