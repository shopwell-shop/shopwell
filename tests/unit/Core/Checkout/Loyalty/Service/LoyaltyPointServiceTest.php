<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsAwardedEvent;
use Shopwell\Core\Checkout\Loyalty\Event\LoyaltyPointsRedeemedEvent;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\LoyaltyPointTransactionTypes;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointService;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltySettings;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The rules of the loyalty program are tested here, the balances and their locking in
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service\LoyaltyLedgerTest}, the account itself in
 * {@see \Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service\LoyaltyAccountServiceTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyPointService::class)]
class LoyaltyPointServiceTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const NOW = '2025-01-01 00:00:00';

    private StaticSystemConfigService $systemConfigService;

    private EventDispatcher $eventDispatcher;

    private LoyaltyLedger&MockObject $ledger;

    private LoyaltyPointLots&Stub $lots;

    private LoggerInterface&Stub $logger;

    private LoyaltyPointService $loyaltyPointService;

    private Context $context;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->eventDispatcher = new EventDispatcher();
        $this->ledger = $this->createMock(LoyaltyLedger::class);
        $this->lots = static::createStub(LoyaltyPointLots::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->context = Context::createDefaultContext();

        $this->loyaltyPointService = $this->service();
    }

    public function testAwardDelegatesToTheLedgerAndAnnouncesTheBooking(): void
    {
        $this->ledger->expects($this->once())
            ->method('bookPoints')
            ->with(
                self::CUSTOMER_ID,
                LoyaltyPointTransactionTypes::AWARD,
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

        $this->systemConfigService->set(LoyaltySettings::CONFIG_KEY_POINTS_VALIDITY_DAYS, 30);

        // the ledger decides nothing about expiry, the rule is passed in
        $expiresAt = $this->capturedExpiresAt();
        static::assertSame('2025-01-31', $expiresAt?->format('Y-m-d'));
    }

    public function testAwardUsesTheConfiguredValidityWhenTheShopNeverSavedOne(): void
    {
        // the settings card shows 365 days, so an unsaved key must expire points after a year and not
        // silently keep them forever
        $this->ledger->expects($this->never())->method('bookPoints');

        $expiresAt = $this->capturedExpiresAt();
        static::assertSame('2026-01-01', $expiresAt?->format('Y-m-d'));
    }

    public function testAwardWithoutValidityDoesNotSetExpiration(): void
    {
        $this->ledger->expects($this->never())->method('bookPoints');

        $this->systemConfigService->set(LoyaltySettings::CONFIG_KEY_POINTS_VALIDITY_DAYS, 0);

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
                LoyaltyPointTransactionTypes::REDEEM,
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
                LoyaltyPointTransactionTypes::ADJUST,
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

    public function testExpireDueDrainsEveryCustomerWithRunOutBatches(): void
    {
        $lots = $this->createMock(LoyaltyPointLots::class);
        $lots->expects($this->once())
            ->method('customersWithExpiredLots')
            ->with(static::isInstanceOf(\DateTimeInterface::class), 500)
            ->willReturn(['customer-a', 'customer-b']);

        $now = new \DateTimeImmutable(self::NOW);

        $this->ledger->expects($this->exactly(2))
            ->method('expireLots')
            ->willReturnCallback(static function (string $customerId, string $type, \DateTimeInterface $moment, Context $context) use ($now): int {
                static::assertSame(LoyaltyPointTransactionTypes::EXPIRE, $type);
                static::assertSame($now, $moment);

                return $customerId === 'customer-a' ? 30 : 12;
            });

        static::assertSame(42, $this->service($lots)->expireDue($this->context, $now));
    }

    public function testExpireDueDoesNothingWhenNothingHasRunOut(): void
    {
        $this->lots->method('customersWithExpiredLots')->willReturn([]);

        $this->ledger->expects($this->never())->method('expireLots');

        static::assertSame(0, $this->loyaltyPointService->expireDue($this->context));
    }

    public function testExpireDueKeepsGoingWhenOneAccountIsBroken(): void
    {
        $this->lots->method('customersWithExpiredLots')->willReturn(['broken', 'healthy']);

        $this->ledger->expects($this->exactly(2))
            ->method('expireLots')
            ->willReturnCallback(
                static function (string $customerId): int {
                    if ($customerId === 'broken') {
                        throw LoyaltyException::lotBalanceMismatch($customerId, 10, 90);
                    }

                    return 25;
                }
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not expire the points of a customer.', static::callback(
                static fn (array $context): bool => $context['customerId'] === 'broken'
            ));

        static::assertSame(25, $this->service(null, $logger)->expireDue($this->context));
    }

    public function testAddTierPointsAnswersTheNewValue(): void
    {
        $this->ledger->expects($this->once())
            ->method('addTierPoints')
            ->with(self::CUSTOMER_ID, 25, $this->context)
            ->willReturn(125);

        static::assertSame(125, $this->loyaltyPointService->addTierPoints(self::CUSTOMER_ID, 25, $this->context));
    }

    /**
     * The doubles the class under test needs, with the two that are only relevant for expiring
     * replaceable: a test that wants to assert on them brings its own.
     */
    private function service(?LoyaltyPointLots $lots = null, ?LoggerInterface $logger = null): LoyaltyPointService
    {
        return new LoyaltyPointService(
            $this->ledger,
            $lots ?? $this->lots,
            new LoyaltySettings($this->systemConfigService),
            $this->eventDispatcher,
            new MockClock(self::NOW),
            $logger ?? $this->logger,
        );
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
            $ledger,
            static::createStub(LoyaltyPointLots::class),
            new LoyaltySettings($this->systemConfigService),
            $this->eventDispatcher,
            new MockClock(self::NOW),
            static::createStub(LoggerInterface::class),
        );

        $service->award(self::CUSTOMER_ID, 10, null, 'key', $this->context);

        return $captured;
    }
}
