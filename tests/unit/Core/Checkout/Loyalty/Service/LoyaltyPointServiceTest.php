<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\TierResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The rules of the loyalty program are tested here, the balances and their locking in
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service\LoyaltyLedgerTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointService::class)]
class LoyaltyPointServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const NOW = '2025-01-01 00:00:00';

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

    private LoyaltyLedger&MockObject $ledger;

    private LoyaltyPointService $loyaltyPointService;

    private Context $context;

    protected function setUp(): void
    {
        $this->customerLoyaltyRepository = StaticEntityRepository::of(CustomerLoyaltyCollection::class);
        $this->tierRepository = StaticEntityRepository::of(LoyaltyTierCollection::class);
        $this->customerRepository = StaticEntityRepository::of(CustomerCollection::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->eventDispatcher = new EventDispatcher();
        $this->ledger = $this->createMock(LoyaltyLedger::class);
        $this->context = Context::createDefaultContext();

        $this->loyaltyPointService = new LoyaltyPointService(
            $this->customerLoyaltyRepository,
            $this->ledger,
            $this->systemConfigService,
            $this->eventDispatcher,
            new MockClock(self::NOW),
            new TierResolver(
                $this->tierRepository,
                $this->customerLoyaltyRepository,
                $this->customerRepository,
                $this->systemConfigService,
                $this->eventDispatcher,
                new MockClock(self::NOW),
            ),
        );
    }

    public function testGetBalanceReturnsZeroWithoutLoyalty(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());

        static::assertSame(0, $this->loyaltyPointService->getBalance(self::CUSTOMER_ID, $this->context));
    }

    public function testGetBalanceReturnsStoredBalance(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $loyalty = new CustomerLoyaltyEntity();
        $loyalty->setId('loyalty-id');
        $loyalty->setPointsBalance(250);
        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection([$loyalty]));

        static::assertSame(250, $this->loyaltyPointService->getBalance(self::CUSTOMER_ID, $this->context));
    }

    public function testProvisionMembershipOpensAMembershipInTheTierThatZeroGrowthUnlocks(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->customerLoyaltyRepository->addSearch(new CustomerLoyaltyCollection());
        $this->tierRepository->addSearch(new LoyaltyTierCollection([$this->createTier('bronze', 1, 0)]));

        $this->loyaltyPointService->provisionMembership(self::CUSTOMER_ID, $this->context);

        $loyalty = $this->customerLoyaltyRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $loyalty['customerId']);
        static::assertSame(0, $loyalty['pointsBalance']);
        static::assertSame('bronze', $loyalty['membershipTierId']);
    }

    public function testAwardDelegatesToTheLedgerAndAnnouncesTheBooking(): void
    {
        $this->ledger->expects($this->once())
            ->method('bookPoints')
            ->with(
                self::CUSTOMER_ID,
                LoyaltyPointService::TRANSACTION_TYPE_AWARD,
                100,
                $this->context,
                'order-1',
                'order-1-earned'
            )
            ->willReturn(150);

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyPointsAwardedEvent::class, static function (LoyaltyPointsAwardedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 100, 'order-1', 'order-1-earned', $this->context);

        static::assertCount(1, $events);
        static::assertSame(100, $events[0]->getPoints());
    }

    public function testAwardIsSilentWhenTheLedgerSkippedTheBookingAsDuplicate(): void
    {
        $this->ledger->expects($this->once())->method('bookPoints')->willReturn(null);

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyPointsAwardedEvent::class, static function (LoyaltyPointsAwardedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 100, null, 'key', $this->context);

        static::assertSame([], $events);
    }

    public function testAwardIgnoresNonPositivePoints(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->loyaltyPointService->award(self::CUSTOMER_ID, 0, null, 'key', $this->context);
    }

    public function testAwardSetsExpirationWhenValidityIsConfigured(): void
    {
        // the booking itself is observed on the local double of capturedExpiresAt()
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->systemConfigService->set(LoyaltyPointService::CONFIG_KEY_POINTS_VALIDITY_DAYS, 30);

        // the ledger decides nothing about expiry, the rule is passed in
        $expiresAt = $this->capturedExpiresAt();
        static::assertSame('2025-01-31', $expiresAt?->format('Y-m-d'));
    }

    public function testAwardWithoutValidityDoesNotSetExpiration(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        static::assertNull($this->capturedExpiresAt());
    }

    public function testRedeemThrowsForNonPositivePoints(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->expectExceptionObject(LoyaltyException::invalidPoints(0));

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 0, null, null, $this->context);
    }

    public function testRedeemDelegatesANegativeBookingThatMayNotOverdraw(): void
    {
        $this->ledger->expects($this->once())
            ->method('bookPoints')
            ->with(
                self::CUSTOMER_ID,
                LoyaltyPointService::TRANSACTION_TYPE_REDEEM,
                -150,
                $this->context,
                'order-1',
                null,
                null,
                null,
                null,
                true
            )
            ->willReturn(50);

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyPointsRedeemedEvent::class, static function (LoyaltyPointsRedeemedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 150, 'order-1', null, $this->context);

        static::assertCount(1, $events);
        static::assertSame(150, $events[0]->getPoints());
    }

    public function testRedeemIsSilentWhenTheLedgerSkippedTheBookingAsDuplicate(): void
    {
        $this->ledger->expects($this->once())->method('bookPoints')->willReturn(null);

        $events = [];
        $this->eventDispatcher->addListener(LoyaltyPointsRedeemedEvent::class, static function (LoyaltyPointsRedeemedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $this->loyaltyPointService->redeem(self::CUSTOMER_ID, 20, 'order-1', 'order-1-redeemed', $this->context);

        static::assertSame([], $events);
    }

    public function testAdjustIgnoresZeroPoints(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->loyaltyPointService->adjust(self::CUSTOMER_ID, 0, 'noop', null, $this->context);
    }

    public function testAdjustPassesTheAdministratorAndTheComment(): void
    {
        $this->ledger->expects($this->once())
            ->method('bookPoints')
            ->with(
                self::CUSTOMER_ID,
                LoyaltyPointService::TRANSACTION_TYPE_ADJUST,
                -40,
                $this->context,
                null,
                null,
                'goodwill',
                'admin-id',
                null,
                true
            )
            ->willReturn(60);

        $this->loyaltyPointService->adjust(self::CUSTOMER_ID, -40, 'goodwill', 'admin-id', $this->context);
    }

    public function testExpireBooksExpiredPoints(): void
    {
        $this->ledger->expects($this->once())
            ->method('bookPoints')
            ->with(
                self::CUSTOMER_ID,
                LoyaltyPointService::TRANSACTION_TYPE_EXPIRE,
                -30,
                $this->context
            )
            ->willReturn(50);

        $this->loyaltyPointService->expire(self::CUSTOMER_ID, 30, $this->context);
    }

    public function testExpireIgnoresNonPositivePoints(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->loyaltyPointService->expire(self::CUSTOMER_ID, 0, $this->context);
    }

    public function testAddGrowthValueAnswersTheNewValue(): void
    {
        $this->ledger->expects($this->once())
            ->method('addGrowthValue')
            ->with(self::CUSTOMER_ID, 25, $this->context)
            ->willReturn(125);

        static::assertSame(125, $this->loyaltyPointService->addGrowthValue(self::CUSTOMER_ID, 25, $this->context));
    }

    private function capturedExpiresAt(): ?\DateTimeInterface
    {
        $captured = null;

        $ledger = static::createStub(LoyaltyLedger::class);
        $ledger->method('bookPoints')->willReturnCallback(
            static function (string $customerId, string $type, int $points, Context $context, ?string $orderId = null, ?string $idempotencyKey = null, ?string $comment = null, ?string $administratorId = null, ?\DateTimeInterface $expiresAt = null) use (&$captured): int {
                $captured = $expiresAt;

                return 10;
            }
        );

        $service = new LoyaltyPointService(
            $this->customerLoyaltyRepository,
            $ledger,
            $this->systemConfigService,
            $this->eventDispatcher,
            new MockClock(self::NOW),
            new TierResolver(
                $this->tierRepository,
                $this->customerLoyaltyRepository,
                $this->customerRepository,
                $this->systemConfigService,
                $this->eventDispatcher,
                new MockClock(self::NOW),
            ),
        );

        $service->award(self::CUSTOMER_ID, 10, null, 'key', $this->context);

        return $captured;
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
