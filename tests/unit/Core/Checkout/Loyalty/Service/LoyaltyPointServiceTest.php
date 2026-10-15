<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
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
#[CoversClass(LoyaltyPointService::class)]
class LoyaltyPointServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const LOYALTY_ID = 'loyalty-id';

    private const NOW = '2025-01-01 00:00:00';

    /**
     * @var StaticEntityRepository<CustomerPointTransactionCollection>
     */
    private StaticEntityRepository $pointTransactionRepository;

    /**
     * @var StaticEntityRepository<CustomerLoyaltyCollection>
     */
    private StaticEntityRepository $customerLoyaltyRepository;

    /**
     * @var StaticEntityRepository<LoyaltyTierCollection>
     */
    private StaticEntityRepository $tierRepository;

    /**
     * @var StaticEntityRepository<CustomerCollection>
     */
    private StaticEntityRepository $customerRepository;

    private StaticSystemConfigService $systemConfigService;

    private EventDispatcher $eventDispatcher;

    private LoyaltyPointService $loyaltyPointService;

    private Context $context;

    protected function setUp(): void
    {
        $this->pointTransactionRepository = StaticEntityRepository::of(CustomerPointTransactionCollection::class);
        $this->customerLoyaltyRepository = StaticEntityRepository::of(CustomerLoyaltyCollection::class);
        $this->tierRepository = StaticEntityRepository::of(LoyaltyTierCollection::class);
        $this->customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->eventDispatcher = new EventDispatcher();
        $this->context = Context::createDefaultContext();

        $this->loyaltyPointService = new LoyaltyPointService(
            $this->pointTransactionRepository,
            $this->customerLoyaltyRepository,
            $this->systemConfigService,
            $this->eventDispatcher,
            new MockClock(self::NOW),
            new TierResolver(
                $this->tierRepository,
                $this->customerLoyaltyRepository,
                $this->customerRepository,
                $this->systemConfigService,
                $this->eventDispatcher,
            ),
        );
    }

    public function testGetBalanceReturnsZeroWithoutLoyalty(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());

        static::assertSame(0, $this->loyaltyPointService->getBalance(self::CUSTOMER_ID, $this->context));
    }

    public function testGetBalanceReturnsStoredBalance(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(250)]));

        static::assertSame(250, $this->loyaltyPointService->getBalance(self::CUSTOMER_ID, $this->context));
    }

    public function testProvisionMembershipOpensAMembershipInTheTierThatZeroGrowthUnlocks(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
        ]));

        $this->loyaltyPointService->provisionMembership(self::CUSTOMER_ID, $this->context);

        $loyalty = $this->customerLoyaltyRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $loyalty['customerId']);
        static::assertSame(0, $loyalty['pointsBalance']);
        static::assertSame('bronze', $loyalty['membershipTierId']);

        // opening a membership books no points, the ledger stays empty
        static::assertSame([], $this->pointTransactionRepository->creates);
    }

    public function testProvisionMembershipLeavesAnExistingMembershipUntouched(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(250)]));

        $this->loyaltyPointService->provisionMembership(self::CUSTOMER_ID, $this->context);

        static::assertSame([], $this->customerLoyaltyRepository->creates);
        static::assertSame([], $this->customerLoyaltyRepository->updates);
        // an existing membership keeps its tier, so no tier is looked up either
        static::assertSame([], $this->tierRepository->searches);
    }

    public function testAwardUpdatesExistingBalance(): void
    {
        $this->pointTransactionRepository->addSearch([]);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(50)]));

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 100, 'order-1', 'order-1-earned', $this->context);

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame(LoyaltyPointService::TRANSACTION_TYPE_AWARD, $transaction['type']);
        static::assertSame(100, $transaction['points']);
        static::assertSame(150, $transaction['balanceAfter']);
        static::assertSame('order-1', $transaction['orderId']);

        $update = $this->customerLoyaltyRepository->updates[0][0];
        static::assertSame(self::LOYALTY_ID, $update['id']);
        static::assertSame(150, $update['pointsBalance']);
    }

    public function testAwardCreatesLoyaltyWhenCustomerHasNone(): void
    {
        $this->pointTransactionRepository->addSearch([]);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection([
            $this->createTier('bronze', 1, 0),
            $this->createTier('silver', 2, 1000),
        ]));

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 100, null, 'award-key', $this->context);

        $loyalty = $this->customerLoyaltyRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $loyalty['customerId']);
        static::assertSame(100, $loyalty['pointsBalance']);
        // a membership that is opened by a booking starts in the tier that zero growth unlocks
        static::assertSame('bronze', $loyalty['membershipTierId']);
    }

    public function testAwardIsIgnoredWhenIdempotencyKeyWasAlreadyBooked(): void
    {
        $this->pointTransactionRepository->addSearch(['existing-transaction']);

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 100, null, 'order-1-earned', $this->context);

        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->customerLoyaltyRepository->updates);
    }

    public function testAwardIgnoresNonPositivePoints(): void
    {
        $this->loyaltyPointService->award(self::CUSTOMER_ID, 0, null, 'key', $this->context);

        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->pointTransactionRepository->searches);
    }

    public function testAwardSetsExpirationWhenValidityIsConfigured(): void
    {
        $this->systemConfigService->set(LoyaltyPointService::CONFIG_KEY_POINTS_VALIDITY_DAYS, 30);

        $this->pointTransactionRepository->addSearch([]);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection());

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 10, null, 'key', $this->context);

        $expiresAt = $this->pointTransactionRepository->creates[0][0]['expiresAt'];
        static::assertInstanceOf(\DateTimeInterface::class, $expiresAt);
        static::assertSame('2025-01-31', $expiresAt->format('Y-m-d'));
    }

    public function testAwardWithoutValidityDoesNotSetExpiration(): void
    {
        $this->pointTransactionRepository->addSearch([]);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection());

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 10, null, 'key', $this->context);

        static::assertNull($this->pointTransactionRepository->creates[0][0]['expiresAt']);
    }

    public function testAwardDispatchesAwardedEvent(): void
    {
        $this->pointTransactionRepository->addSearch([]);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection());

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyPointsAwardedEvent::class, static function (LoyaltyPointsAwardedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 75, 'order-2', 'key', $this->context);

        static::assertCount(1, $events);
        static::assertSame(self::CUSTOMER_ID, $events[0]->getCustomerId());
        static::assertSame(75, $events[0]->getPoints());
        static::assertSame('order-2', $events[0]->getOrderId());
        static::assertSame($this->context, $events[0]->getContext());
    }

    public function testRedeemBooksNegativeTransaction(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(200)]));
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(200)]));

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 150, 'order-1', null, $this->context);

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame(LoyaltyPointService::TRANSACTION_TYPE_REDEEM, $transaction['type']);
        static::assertSame(-150, $transaction['points']);
        static::assertSame(50, $transaction['balanceAfter']);
        static::assertSame('order-1', $transaction['orderId']);

        static::assertSame(50, $this->customerLoyaltyRepository->updates[0][0]['pointsBalance']);
    }

    public function testRedeemThrowsForNonPositivePoints(): void
    {
        $this->expectExceptionObject(LoyaltyException::invalidPoints(0));

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 0, null, null, $this->context);
    }

    public function testRedeemThrowsWhenBalanceIsInsufficient(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(50)]));

        $this->expectExceptionObject(LoyaltyException::insufficientPoints(self::CUSTOMER_ID, 100, 50));

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 100, null, null, $this->context);
    }

    public function testRedeemIsIgnoredWhenIdempotencyKeyWasAlreadyBooked(): void
    {
        $this->pointTransactionRepository->addSearch(['existing-transaction']);

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 100, 'order-1', 'order-1-redeemed', $this->context);

        static::assertSame([], $this->pointTransactionRepository->creates);
    }

    public function testRedeemDispatchesRedeemedEvent(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(200)]));
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(200)]));

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyPointsRedeemedEvent::class, static function (LoyaltyPointsRedeemedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 20, 'order-3', null, $this->context);

        static::assertCount(1, $events);
        static::assertSame(20, $events[0]->getPoints());
        static::assertSame('order-3', $events[0]->getOrderId());
    }

    public function testAdjustThrowsWhenResultingBalanceWouldBeNegative(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(30)]));

        $this->expectExceptionObject(LoyaltyException::insufficientPoints(self::CUSTOMER_ID, 50, 30));

        $this->loyaltyPointService->adjust(self::CUSTOMER_ID, -50, 'correction', 'admin-id', $this->context);
    }

    public function testAdjustBooksManualCorrection(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(100)]));
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(100)]));

        $this->loyaltyPointService->adjust(self::CUSTOMER_ID, -40, 'goodwill', 'admin-id', $this->context);

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame(LoyaltyPointService::TRANSACTION_TYPE_ADJUST, $transaction['type']);
        static::assertSame(-40, $transaction['points']);
        static::assertSame(60, $transaction['balanceAfter']);
        static::assertSame('goodwill', $transaction['comment']);
        static::assertSame('admin-id', $transaction['administratorId']);
    }

    public function testAdjustIgnoresZeroPoints(): void
    {
        $this->loyaltyPointService->adjust(self::CUSTOMER_ID, 0, 'noop', null, $this->context);

        static::assertSame([], $this->pointTransactionRepository->creates);
    }

    public function testExpireBooksExpiredPointsTransaction(): void
    {
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$this->createLoyalty(80)]));

        $this->loyaltyPointService->expire(self::CUSTOMER_ID, 30, $this->context);

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame(LoyaltyPointService::TRANSACTION_TYPE_EXPIRE, $transaction['type']);
        static::assertSame(-30, $transaction['points']);
        static::assertSame(50, $transaction['balanceAfter']);
        static::assertSame(50, $this->customerLoyaltyRepository->updates[0][0]['pointsBalance']);
    }

    public function testExpireIgnoresNonPositivePoints(): void
    {
        $this->loyaltyPointService->expire(self::CUSTOMER_ID, 0, $this->context);

        static::assertSame([], $this->pointTransactionRepository->creates);
    }

    private function createLoyalty(int $balance): CustomerLoyaltyEntity
    {
        $loyalty = new CustomerLoyaltyEntity();
        $loyalty->setId(self::LOYALTY_ID);
        $loyalty->setCustomerId(self::CUSTOMER_ID);
        $loyalty->setPointsBalance($balance);

        return $loyalty;
    }

    private function createTier(string $technicalName, int $level, int $minGrowthValue): LoyaltyTierEntity
    {
        $tier = new LoyaltyTierEntity();
        $tier->setId($technicalName);
        $tier->setTechnicalName($technicalName);
        $tier->setLevel($level);
        $tier->setMinGrowthValue($minGrowthValue);
        $tier->setActive(true);

        return $tier;
    }
}
