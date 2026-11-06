<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyTierChangedEvent;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
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
     * @var StaticEntityRepository<LoyaltyAccountCollection>
     */
    private StaticEntityRepository $loyaltyAccountRepository;

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
        $this->loyaltyAccountRepository = StaticEntityRepository::of(LoyaltyAccountCollection::class);
        $this->customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->eventDispatcher = new EventDispatcher();
        $this->context = Context::createDefaultContext();

        $this->clock = new MockClock(self::NOW);

        $this->tierResolver = new TierResolver(
            $this->tierRepository,
            $this->loyaltyAccountRepository,
            $this->customerRepository,
            new LoyaltySettings($this->systemConfigService),
            $this->eventDispatcher,
            $this->clock,
        );
    }

    public function testResolveDoesNothingWhenCustomerHasNoLoyalty(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection());

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->loyaltyAccountRepository->updates);
        static::assertSame([], $this->tierRepository->searches);
    }

    public function testResolveAssignsTheHighestMatchingTier(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty(null)]));
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

        static::assertSame(self::LOYALTY_ID, $this->loyaltyAccountRepository->updates[0][0]['id']);
        static::assertSame('silver', $this->loyaltyAccountRepository->updates[0][0]['tierId']);

        static::assertCount(1, $events);
        static::assertNull($events[0]->getPreviousTierId());
        static::assertSame('silver', $events[0]->getNewTierId());
    }

    /**
     * A running membership outranks the tier points, so no downgrade happens even though the
     * tier points alone would only reach a lower tier.
     */
    public function testResolveKeepsThePaidTierWhileTheMembershipRuns(): void
    {
        $paidTier = $this->createTier('silver', 2, 1000);
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([
            $this->createLoyalty('silver', $paidTier, '2026-12-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$paidTier]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 0, $this->context);

        static::assertSame([], $this->loyaltyAccountRepository->updates);
    }

    /**
     * Once the membership ended the tier points decide again, which is why the end date has to be
     * checked on every resolution instead of in a clean up task.
     */
    public function testResolveFallsBackToTheTierPointsOnceTheMembershipEnded(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([
            $this->createLoyalty('gold', $this->createTier('gold', 3, 5000), '2026-01-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('bronze', 1, 0)]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 100, $this->context);

        static::assertSame('bronze', $this->loyaltyAccountRepository->updates[0][0]['tierId']);
    }

    public function testCurrentTierUsesTheRunningMembership(): void
    {
        $paidTier = $this->createTier('silver', 2, 1000);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([
            $this->createLoyalty('silver', $paidTier, '2026-12-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$paidTier]));

        static::assertSame($paidTier->getId(), $this->tierResolver->currentTier(self::CUSTOMER_ID, $this->context)?->getId());
    }

    public function testCurrentTierFallsBackAfterTheMembershipEnded(): void
    {
        $bronze = $this->createTier('bronze', 1, 0);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([
            $this->createLoyalty('gold', $this->createTier('gold', 3, 5000), '2026-01-01 00:00:00'),
        ]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$bronze]));

        static::assertSame($bronze->getId(), $this->tierResolver->currentTier(self::CUSTOMER_ID, $this->context)?->getId());
    }

    public function testResolveDoesNothingWhenTierIsUnchanged(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty('silver')]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->loyaltyAccountRepository->updates);
        static::assertSame([], $this->customerRepository->updates);
    }

    public function testResolveClearsTierWhenNoTierMatches(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty('gold')]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('gold', 3, 5000),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 500, $this->context);

        static::assertNull($this->loyaltyAccountRepository->updates[0][0]['tierId']);
    }

    public function testResolveSyncsCustomerGroupWhenConfigured(): void
    {
        $this->systemConfigService->set(LoyaltySettings::CONFIG_KEY_TIER_AUTO_GROUP_SYNC, true);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000, 'customer-group-id'),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame(self::CUSTOMER_ID, $this->customerRepository->updates[0][0]['id']);
        static::assertSame('customer-group-id', $this->customerRepository->updates[0][0]['groupId']);
    }

    public function testResolveDoesNotSyncCustomerGroupWhenDisabled(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000, 'customer-group-id'),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->customerRepository->updates);
    }

    public function testResolveDoesNotSyncCustomerGroupWhenTierHasNone(): void
    {
        $this->systemConfigService->set(LoyaltySettings::CONFIG_KEY_TIER_AUTO_GROUP_SYNC, true);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000),
        ]));

        $this->tierResolver->resolve(self::CUSTOMER_ID, 1500, $this->context);

        static::assertSame([], $this->customerRepository->updates);
    }

    public function testCurrentTierFollowsTheTierPoints(): void
    {
        $tier = $this->createTier('silver', 2, 1000);

        $loyalty = $this->createLoyalty(null);
        $loyalty->setTierPoints(1500);

        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$loyalty]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$tier]));

        static::assertSame($tier->getId(), $this->tierResolver->currentTier(self::CUSTOMER_ID, $this->context)?->getId());
    }

    public function testCurrentTierIsNullWithoutLoyalty(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection());

        static::assertNull($this->tierResolver->currentTier(self::CUSTOMER_ID, $this->context));
    }

    public function testCurrentTierIsNullWithoutTier(): void
    {
        $this->loyaltyAccountRepository->addSearch(new LoyaltyAccountCollection([$this->createLoyalty(null)]));
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('silver', 2, 1000)]));

        static::assertNull($this->tierResolver->currentTier(self::CUSTOMER_ID, $this->context));
    }

    /**
     * @return iterable<string, array{tierPoints: int, expectedTierId: string}>
     */
    public static function tierPointss(): iterable
    {
        yield 'zero tier points stays in the entry tier' => ['tierPoints' => 0, 'expectedTierId' => 'bronze'];
        yield 'tier points above the second threshold reaches the second tier' => ['tierPoints' => 1500, 'expectedTierId' => 'silver'];
    }

    #[DataProvider('tierPointss')]
    public function testFindTierIdForTierPointsAnswersTheTierThatResolveWouldAssign(int $tierPoints, string $expectedTierId): void
    {
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
            $this->createTier('gold', 3, 5000),
        ]));

        static::assertSame($expectedTierId, $this->tierResolver->findTierIdForTierPoints($tierPoints, $this->context));
    }

    public function testFindTierIdForTierPointsIsNullWhileNoTierIsReached(): void
    {
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('silver', 2, 1000),
        ]));

        static::assertNull($this->tierResolver->findTierIdForTierPoints(0, $this->context));
    }

    private function createLoyalty(?string $tierId, ?LoyaltyTierEntity $tier = null, ?string $validUntil = null): LoyaltyAccountEntity
    {
        $loyalty = new LoyaltyAccountEntity();
        $loyalty->setId(self::LOYALTY_ID);
        $loyalty->setCustomerId(self::CUSTOMER_ID);
        $loyalty->setTierId($tierId);

        if ($validUntil !== null) {
            $loyalty->setMembershipPaidUntil(new \DateTimeImmutable($validUntil));
        }

        if ($tier !== null) {
            $loyalty->setTier($tier);
        }

        return $loyalty;
    }

    private function createTier(string $technicalName, int $level, ?int $minTierPoints, ?string $customerGroupId = null): LoyaltyTierEntity
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId($technicalName);
        $tier->setTechnicalName($technicalName);
        $tier->setLevel($level);
        $tier->setMinTierPoints($minTierPoints);
        $tier->setCustomerGroupId($customerGroupId);
        $tier->setActive(true);

        return $tier;
    }
}
