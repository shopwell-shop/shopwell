<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerPointTransaction\CustomerPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyLedger::class)]
class LoyaltyLedgerTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const ACCOUNT_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const TIER_ID = 'e1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const NEW_TIER_ID = 'f1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const NOW = '2026-06-01 12:00:00';

    /**
     * @var StaticEntityRepository<CustomerLoyaltyCollection>
     */
    private StaticEntityRepository $customerLoyaltyRepository;

    /**
     * @var StaticEntityRepository<CustomerPointTransactionCollection>
     */
    private StaticEntityRepository $pointTransactionRepository;

    private Connection&Stub $connection;

    /**
     * Answers the row the ledger locks; tests that need another one set it instead of replacing the
     * connection double.
     *
     * @var (\Closure(): mixed)|null
     */
    private ?\Closure $rowResolver = null;

    private ClockInterface $clock;

    private LoyaltyLedger $ledger;

    private Context $context;

    protected function setUp(): void
    {
        $this->customerLoyaltyRepository = StaticEntityRepository::of(CustomerLoyaltyCollection::class);
        $this->pointTransactionRepository = StaticEntityRepository::of(CustomerPointTransactionCollection::class);
        $this->connection = static::createStub(Connection::class);
        $this->context = Context::createDefaultContext();

        $this->connection->method('transactional')->willReturnCallback(
            static fn (callable $callback) => $callback()
        );

        $this->connection->method('fetchAssociative')->willReturnCallback(function (): mixed {
            if ($this->rowResolver !== null) {
                return ($this->rowResolver)();
            }

            return [
                'id' => self::ACCOUNT_ID,
                'points_balance' => 200,
                'growth_value' => 500,
                'membership_tier_id' => null,
                'membership_valid_until' => null,
            ];
        });

        $this->clock = new MockClock(self::NOW);

        $this->ledger = new LoyaltyLedger(
            $this->customerLoyaltyRepository,
            $this->pointTransactionRepository,
            $this->connection,
            $this->clock,
        );
    }

    public function testBooksPointsAndMovesTheBalance(): void
    {
        $balanceAfter = $this->ledger->bookPoints(
            self::CUSTOMER_ID,
            'award',
            100,
            $this->context,
            orderId: self::ACCOUNT_ID,
            idempotencyKey: 'order-1-earned'
        );

        static::assertSame(300, $balanceAfter);

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $transaction['customerId']);
        static::assertSame('award', $transaction['type']);
        static::assertSame(100, $transaction['points']);
        static::assertSame(300, $transaction['balanceAfter']);
        static::assertSame('order-1-earned', $transaction['idempotencyKey']);

        static::assertSame(self::ACCOUNT_ID, $this->customerLoyaltyRepository->updates[0][0]['id']);
        static::assertSame(300, $this->customerLoyaltyRepository->updates[0][0]['pointsBalance']);
    }

    public function testSkipsABookingWhoseKeyWasAlreadyBooked(): void
    {
        $this->connection->method('fetchOne')->willReturn(1);

        $balanceAfter = $this->ledger->bookPoints(
            self::CUSTOMER_ID,
            'award',
            100,
            $this->context,
            idempotencyKey: 'order-1-earned'
        );

        static::assertNull($balanceAfter);
        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->customerLoyaltyRepository->updates);
    }

    public function testRefusesToTakeTheBalanceBelowZero(): void
    {
        $this->expectExceptionObject(LoyaltyException::insufficientPoints(self::CUSTOMER_ID, 300, 200));

        try {
            $this->ledger->bookPoints(
                self::CUSTOMER_ID,
                'redeem',
                -300,
                $this->context,
                rejectNegativeBalance: true
            );
        } finally {
            static::assertSame([], $this->pointTransactionRepository->creates);
            static::assertSame([], $this->customerLoyaltyRepository->updates);
        }
    }

    public function testOpensTheAccountWhenTheCustomerHasNone(): void
    {
        $calls = 0;
        $this->rowResolver = function () use (&$calls): mixed {
            ++$calls;

            return $calls === 1 ? false : [
                'id' => self::ACCOUNT_ID,
                'points_balance' => 0,
                'growth_value' => 0,
                'membership_tier_id' => null,
                'membership_valid_until' => null,
            ];
        };

        $this->ledger->bookPoints(self::CUSTOMER_ID, 'award', 10, $this->context);

        $account = $this->customerLoyaltyRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $account['customerId']);
        static::assertSame(0, $account['pointsBalance']);
        static::assertSame(10, $this->pointTransactionRepository->creates[0][0]['balanceAfter']);
    }

    public function testAddsGrowthValue(): void
    {
        $growthValue = $this->ledger->addGrowthValue(self::CUSTOMER_ID, 250, $this->context);

        static::assertSame(750, $growthValue);
        static::assertSame(750, $this->customerLoyaltyRepository->updates[0][0]['growthValue']);
    }

    public function testActivatesAMembershipStartingNow(): void
    {
        $period = $this->ledger->activateMembership(self::CUSTOMER_ID, self::TIER_ID, 30, $this->context);

        $update = $this->customerLoyaltyRepository->updates[0][0];
        static::assertSame(self::TIER_ID, $update['membershipTierId']);
        static::assertEquals(new \DateTimeImmutable(self::NOW), $period['startsAt']);
        static::assertEquals(new \DateTimeImmutable('2026-07-01 12:00:00'), $period['expiresAt']);
        static::assertSame($period['expiresAt'], $update['membershipValidUntil']);
    }

    public function testRenewingTheSameTierContinuesWhereItEnds(): void
    {
        $this->rowResolver = static fn (): array => [
            'id' => self::ACCOUNT_ID,
            'points_balance' => 0,
            'growth_value' => 0,
            'membership_tier_id' => self::TIER_ID,
            'membership_valid_until' => '2026-12-01 00:00:00',
        ];

        $period = $this->ledger->activateMembership(self::CUSTOMER_ID, self::TIER_ID, 30, $this->context);

        static::assertEquals(new \DateTimeImmutable('2026-12-01 00:00:00'), $period['startsAt']);
        static::assertEquals(new \DateTimeImmutable('2026-12-31 00:00:00'), $period['expiresAt']);
    }

    public function testBuyingAnotherTierStartsNowInsteadOfStacking(): void
    {
        $this->rowResolver = static fn (): array => [
            'id' => self::ACCOUNT_ID,
            'points_balance' => 0,
            'growth_value' => 0,
            'membership_tier_id' => self::TIER_ID,
            'membership_valid_until' => '2026-12-01 00:00:00',
        ];

        $period = $this->ledger->activateMembership(self::CUSTOMER_ID, self::NEW_TIER_ID, 30, $this->context);

        static::assertEquals(new \DateTimeImmutable(self::NOW), $period['startsAt']);
        static::assertSame(self::NEW_TIER_ID, $this->customerLoyaltyRepository->updates[0][0]['membershipTierId']);
    }

    public function testAnEndedMembershipDoesNotContinue(): void
    {
        $this->rowResolver = static fn (): array => [
            'id' => self::ACCOUNT_ID,
            'points_balance' => 0,
            'growth_value' => 0,
            'membership_tier_id' => self::TIER_ID,
            'membership_valid_until' => '2026-01-01 00:00:00',
        ];

        $period = $this->ledger->activateMembership(self::CUSTOMER_ID, self::TIER_ID, 30, $this->context);

        static::assertEquals(new \DateTimeImmutable(self::NOW), $period['startsAt']);
    }
}
