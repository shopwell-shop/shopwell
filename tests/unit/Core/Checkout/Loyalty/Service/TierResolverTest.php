<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyTierChangedEvent;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(TierResolver::class)]
class TierResolverTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const LOYALTY_ID = 'loyalty-id';

    private const NOW = '2026-06-01 12:00:00';

    /**
     * @var StaticEntityRepository<LoyaltyTierCollection>
     */
    private StaticEntityRepository $tierRepository;

    /**
     * @var StaticEntityRepository<CustomerLoyaltyCollection>
     */
    private StaticEntityRepository $customerLoyaltyRepository;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    private StaticSystemConfigService $systemConfigService;

    private EventDispatcher $eventDispatcher;

    private ClockInterface $clock;

    private TierResolver $tierResolver;

    private Context $context;

    protected function setUp(): void
    {
        $this->tierRepository = StaticEntityRepository::of(LoyaltyTierCollection::class);
        $this->customerLoyaltyRepository = StaticEntityRepository::of(CustomerLoyaltyCollection::class);
        $this->customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->eventDispatcher = new EventDispatcher();
        $this->context = Context::createDefaultContext();

        $this->clock = new MockClock(self::NOW);

        $this->tierResolver = new TierResolver(
            $this->tierRepository,
            $this->customerLoyaltyRepository,
            $this->customerRepository,
            $this->systemConfigService,
            $this->eventDispatcher,
            $this->clock,
        );
    }

    public function testResolveDoesNothingWhenCustomerHasNoLoyalty(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->customerLoyaltyRepository->updates);
        static::assertSame([], $this->tierRepository->searches);
    }

    public function testResolveAssignsTheHighestMatchingTier(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
            $this->createTier('gold', 3, 5000),
        ]));

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyTierChangedEvent::class, static function (LoyaltyTierChangedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame(self::LOYALTY_ID, $this->customerLoyaltyRepository->updates[0][0]['id']);
        static::assertSame('silver', $this->customerLoyaltyRepository->updates[0][0]['membershipTierId']);

        static::assertCount(1, $events);
        static::assertNull($events[0]->getPreviousTierId());
        static::assertSame('silver', $events[0]->getNewTierId());
    }

    /**
     * A running membership outranks the growth value, so no downgrade happens even though the
     * growth value alone would only reach a lower tier.
     */
    public function testResolveKeepsThePaidTierWhileTheMembershipRuns(): void
    {
        $paidTier = $this->createTier('silver', 2, 1000);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([
            $this->createLoyalty('silver', $paidTier, '2026-12-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$paidTier]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 0, $this->context);

        static::assertSame([], $this->customerLoyaltyRepository->updates);
    }

    /**
     * Once the membership ended the growth value decides again, which is why the end date has to be
     * checked on every resolution instead of in a clean up task.
     */
    public function testResolveFallsBackToTheGrowthValueOnceTheMembershipEnded(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([
            $this->createLoyalty('gold', $this->createTier('gold', 3, 5000), '2026-01-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('bronze', 1, 0)]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 100, $this->context);

        static::assertSame('bronze', $this->customerLoyaltyRepository->updates[0][0]['membershipTierId']);
    }

    public function testGetPointsMultiplierUsesTheRunningMembership(): void
    {
        $paidTier = $this->createTier('silver', 2, 1000);
        $paidTier->setPointsMultiplier(1.5);

        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([
            $this->createLoyalty('silver', $paidTier, '2026-12-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$paidTier]));

        static::assertSame(1.5, $this->tierResolver->getPointsMultiplier(self::CUSTOMER_ID, $this->context));
    }

    public function testGetPointsMultiplierFallsBackAfterTheMembershipEnded(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([
            $this->createLoyalty('gold', $this->createTier('gold', 3, 5000), '2026-01-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('bronze', 1, 0)]));

        static::assertSame(1.0, $this->tierResolver->getPointsMultiplier(self::CUSTOMER_ID, $this->context));
    }

    public function testResolveDoesNothingWhenTierIsUnchanged(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty('silver')]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->customerLoyaltyRepository->updates);
        static::assertSame([], $this->customerRepository->updates);
    }

    public function testResolveClearsTierWhenNoTierMatches(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty('gold')]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('gold', 3, 5000),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 500, $this->context);

        static::assertNull($this->customerLoyaltyRepository->updates[0][0]['membershipTierId']);
    }

    public function testResolveSyncsCustomerGroupWhenConfigured(): void
    {
        $this->systemConfigService->set(TierResolver::CONFIG_KEY_TIER_AUTO_GROUP_SYNC, true);

        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000, 'customer-group-id'),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame(self::CUSTOMER_ID, $this->customerRepository->updates[0][0]['id']);
        static::assertSame('customer-group-id', $this->customerRepository->updates[0][0]['groupId']);
    }

    public function testResolveDoesNotSyncCustomerGroupWhenDisabled(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000, 'customer-group-id'),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->customerRepository->updates);
    }

    public function testResolveDoesNotSyncCustomerGroupWhenTierHasNone(): void
    {
        $this->systemConfigService->set(TierResolver::CONFIG_KEY_TIER_AUTO_GROUP_SYNC, true);

        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->customerRepository->updates);
    }

    public function testGetPointsMultiplierReturnsTierMultiplier(): void
    {
        $tier = $this->createTier('silver', 2, 1000);
        $tier->setPointsMultiplier(2.0);

        $loyalty = $this->createLoyalty(null);
        $loyalty->setGrowthValue(1500);

        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$loyalty]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$tier]));

        static::assertSame(2.0, $this->tierResolver->getPointsMultiplier(self::CUSTOMER_ID, $this->context));
    }

    public function testGetPointsMultiplierDefaultsToOneWithoutLoyalty(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());

        static::assertSame(1.0, $this->tierResolver->getPointsMultiplier(self::CUSTOMER_ID, $this->context));
    }

    public function testGetPointsMultiplierDefaultsToOneWithoutTier(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('silver', 2, 1000)]));

        static::assertSame(1.0, $this->tierResolver->getPointsMultiplier(self::CUSTOMER_ID, $this->context));
    }

    /**
     * @return iterable<string, array{growthValue: int, expectedTierId: string}>
     */
    public static function growthValues(): iterable
    {
        yield 'zero growth stays in the entry tier' => ['growthValue' => 0, 'expectedTierId' => 'bronze'];
        yield 'growth above the second threshold reaches the second tier' => ['growthValue' => 1500, 'expectedTierId' => 'silver'];
    }

    #[DataProvider('growthValues')]
    public function testFindTierIdForGrowthValueAnswersTheTierThatResolveWouldAssign(int $growthValue, string $expectedTierId): void
    {
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
            $this->createTier('gold', 3, 5000),
        ]));

        static::assertSame($expectedTierId, $this->tierResolver->findTierIdForGrowthValue($growthValue, $this->context));
    }

    public function testFindTierIdForGrowthValueIsNullWhileNoTierIsReached(): void
    {
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000),
        ]));

        static::assertNull($this->tierResolver->findTierIdForGrowthValue(0, $this->context));
    }

    private function createLoyalty(?string $tierId, ?LoyaltyTierEntity $tier = null, ?string $validUntil = null): CustomerLoyaltyEntity
    {
        $loyalty = new CustomerLoyaltyEntity();
        $loyalty->setId(self::LOYALTY_ID);
        $loyalty->setCustomerId(self::CUSTOMER_ID);
        $loyalty->setMembershipTierId($tierId);

        if ($validUntil !== null) {
            $loyalty->setMembershipValidUntil(new \DateTimeImmutable($validUntil));
        }

        if ($tier !== null) {
            $loyalty->setMembershipTier($tier);
        }

        return $loyalty;
    }

    private function createTier(string $technicalName, int $level, ?int $minGrowthValue, ?string $customerGroupId = null): LoyaltyTierEntity
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId($technicalName);
        $tier->setTechnicalName($technicalName);
        $tier->setLevel($level);
        $tier->setMinGrowthValue($minGrowthValue);
        $tier->setCustomerGroupId($customerGroupId);
        $tier->setActive(true);

        return $tier;
    }
}
