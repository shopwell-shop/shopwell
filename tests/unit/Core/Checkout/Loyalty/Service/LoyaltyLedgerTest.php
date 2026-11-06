<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLot\LoyaltyPointLotCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointLotUse\LoyaltyPointLotUseCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyPointTransaction\LoyaltyPointTransactionCollection;
use Shopwell\Core\Checkout\Loyalty\LoyaltyException;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyLedger;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyPointLots;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * The ledger writes three things for one booking: the booking, the balance and the batches. The tests
 * pin down that all three move together and that the batches are spent oldest-expiry-first - the
 * decision itself is covered by {@see LoyaltyPointLotsTest}.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LoyaltyLedger::class)]
class LoyaltyLedgerTest extends TestCase
{
    private const CUSTOMER_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const NOW = '2026-11-02 12:00:00';

    private const ACCOUNT_ID = 'c1b2c3d4e5f60718293a4b5c6d7e8f90';

    private const FIRST_LOT_ID = '11b2c3d4e5f60718293a4b5c6d7e8f90';

    private const SECOND_LOT_ID = '22b2c3d4e5f60718293a4b5c6d7e8f90';

    /**
     * @var StaticEntityRepository<LoyaltyAccountCollection>
     */
    private StaticEntityRepository $loyaltyAccountRepository;

    /**
     * @var StaticEntityRepository<LoyaltyPointTransactionCollection>
     */
    private StaticEntityRepository $pointTransactionRepository;

    /**
     * @var StaticEntityRepository<LoyaltyPointLotCollection>
     */
    private StaticEntityRepository $lotRepository;

    /**
     * @var StaticEntityRepository<LoyaltyPointLotUseCollection>
     */
    private StaticEntityRepository $useRepository;

    private Connection&Stub $connection;

    /**
     * Answers the row the ledger locks; tests that need another one set it instead of replacing the
     * connection double.
     *
     * @var (\Closure(): mixed)|null
     */
    private ?\Closure $rowResolver = null;

    /**
     * The batches the next `FOR UPDATE` on the lots answers, in the order the query would return them.
     *
     * @var list<array{id: string, remaining: int}>
     */
    private array $openLots = [];

    /**
     * The withdrawals the next `FOR UPDATE` on the uses answers, one row per batch the order took from.
     *
     * @var list<array{
     *     useId: string,
     *     lotId: string,
     *     points: int,
     *     returned: int,
     *     remaining: int,
     *     expiresAt: \DateTimeInterface|null,
     *     expiredAt: \DateTimeInterface|null
     * }>
     */
    private array $openUses = [];

    private int $lotTotal = 0;

    private bool $keyAlreadyBooked = false;

    private LoyaltyLedger $ledger;

    private Context $context;

    protected function setUp(): void
    {
        $this->loyaltyAccountRepository = StaticEntityRepository::of(LoyaltyAccountCollection::class);
        $this->pointTransactionRepository = StaticEntityRepository::of(LoyaltyPointTransactionCollection::class);
        $this->lotRepository = StaticEntityRepository::of(LoyaltyPointLotCollection::class);
        $this->useRepository = StaticEntityRepository::of(LoyaltyPointLotUseCollection::class);
        $this->connection = static::createStub(Connection::class);
        $this->context = Context::createDefaultContext();

        $this->connection->method('transactional')->willReturnCallback(
            static fn (callable $callback) => $callback()
        );

        $this->connection->method('fetchAssociative')->willReturnCallback(function (): mixed {
            if ($this->rowResolver !== null) {
                return ($this->rowResolver)();
            }

            return $this->row();
        });

        $this->connection->method('fetchOne')->willReturnCallback(function (string $sql): mixed {
            if (str_contains($sql, 'SUM(`remaining_points`)')) {
                return $this->lotTotal;
            }

            return $this->keyAlreadyBooked ? 1 : 0;
        });

        $this->connection->method('fetchAllAssociative')->willReturnCallback(function (string $sql): array {
            if (str_contains($sql, 'loyalty_point_lot_use')) {
                return array_map(
                    fn (array $use): array => [
                        'id' => Uuid::fromHexToBytes($use['useId']),
                        'lot_id' => Uuid::fromHexToBytes($use['lotId']),
                        'points' => $use['points'],
                        'returned_points' => $use['returned'],
                        'remaining_points' => $use['remaining'],
                        'expires_at' => $use['expiresAt']?->format('Y-m-d H:i:s.v'),
                        'expired_at' => $use['expiredAt']?->format('Y-m-d H:i:s.v'),
                    ],
                    $this->openUses
                );
            }

            return array_map(
                static fn (array $lot): array => [
                    'id' => Uuid::fromHexToBytes($lot['id']),
                    'remaining_points' => $lot['remaining'],
                ],
                $this->openLots
            );
        });

        $this->ledger = new LoyaltyLedger(
            $this->loyaltyAccountRepository,
            $this->pointTransactionRepository,
            $this->connection,
            new RowLock($this->connection),
            new LoyaltyPointLots($this->connection, $this->lotRepository, $this->useRepository, new MockClock(self::NOW)),
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

        static::assertSame(self::ACCOUNT_ID, $this->loyaltyAccountRepository->updates[0][0]['id']);
        static::assertSame(300, $this->loyaltyAccountRepository->updates[0][0]['pointsBalance']);
    }

    public function testABookingThatAddsPointsOpensABatch(): void
    {
        $this->ledger->bookPoints(self::CUSTOMER_ID, 'award', 100, $this->context, orderId: self::ACCOUNT_ID);

        static::assertCount(1, $this->lotRepository->creates);
        static::assertSame(100, $this->lotRepository->creates[0][0]['points']);
        static::assertSame(100, $this->lotRepository->creates[0][0]['remainingPoints']);
        static::assertSame(self::ACCOUNT_ID, $this->lotRepository->creates[0][0]['orderId']);
    }

    public function testSkipsABookingWhoseKeyWasAlreadyBooked(): void
    {
        $this->keyAlreadyBooked = true;

        $balanceAfter = $this->ledger->bookPoints(
            self::CUSTOMER_ID,
            'award',
            100,
            $this->context,
            idempotencyKey: 'order-1-earned'
        );

        static::assertNull($balanceAfter);
        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->loyaltyAccountRepository->updates);
        static::assertSame([], $this->lotRepository->creates);
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
            static::assertSame([], $this->loyaltyAccountRepository->updates);
        }
    }

    public function testASpentBookingTakesTheBatchThatExpiresFirst(): void
    {
        $this->lotTotal = 200;
        $this->openLots = [
            ['id' => self::FIRST_LOT_ID, 'remaining' => 100],
            ['id' => self::SECOND_LOT_ID, 'remaining' => 100],
        ];

        $this->ledger->bookPoints(
            self::CUSTOMER_ID,
            'redeem',
            -150,
            $this->context,
            rejectNegativeBalance: true
        );

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame(-150, $transaction['points']);
        static::assertSame(50, $transaction['balanceAfter']);

        $uses = $this->useRepository->creates[0];
        static::assertCount(2, $uses);
        static::assertSame(self::FIRST_LOT_ID, $uses[0]['lotId']);
        static::assertSame(100, $uses[0]['points']);
        static::assertSame(self::SECOND_LOT_ID, $uses[1]['lotId']);
        static::assertSame(50, $uses[1]['points']);

        $updates = $this->lotRepository->updates[0];
        static::assertSame(0, $updates[0]['remainingPoints']);
        static::assertSame(50, $updates[1]['remainingPoints']);
    }

    public function testReportsABalanceThatTheBatchesCannotCover(): void
    {
        $this->lotTotal = 100;

        $this->expectExceptionObject(LoyaltyException::lotBalanceMismatch(self::CUSTOMER_ID, 100, 200));

        $this->ledger->bookPoints(
            self::CUSTOMER_ID,
            'redeem',
            -150,
            $this->context,
            rejectNegativeBalance: true
        );
    }

    public function testExpiringDrainsOnlyTheBatchesThatHaveRunOut(): void
    {
        $this->lotTotal = 200;
        $this->openLots = [
            ['id' => self::FIRST_LOT_ID, 'remaining' => 40],
            ['id' => self::SECOND_LOT_ID, 'remaining' => 20],
        ];

        $now = new \DateTimeImmutable('2026-11-02 03:00:00');

        static::assertSame(60, $this->ledger->expireLots(self::CUSTOMER_ID, 'expire', $now, $this->context));

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame('expire', $transaction['type']);
        static::assertSame(-60, $transaction['points']);
        static::assertSame(140, $transaction['balanceAfter']);

        $updates = $this->lotRepository->updates[0];
        static::assertSame(0, $updates[0]['remainingPoints']);
        static::assertSame($now, $updates[0]['expiredAt']);
        static::assertSame(0, $updates[1]['remainingPoints']);
    }

    public function testExpiringNothingWritesNoBooking(): void
    {
        $this->lotTotal = 200;
        $this->openLots = [];

        static::assertSame(0, $this->ledger->expireLots(
            self::CUSTOMER_ID,
            'expire',
            new \DateTimeImmutable('2026-11-02 03:00:00'),
            $this->context
        ));

        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->loyaltyAccountRepository->updates);
    }

    public function testRefusesToExpireWhenTheBatchesDoNotAddUpToTheBalance(): void
    {
        $this->lotTotal = 50;

        $this->expectExceptionObject(LoyaltyException::lotBalanceMismatch(self::CUSTOMER_ID, 50, 200));

        $this->ledger->expireLots(
            self::CUSTOMER_ID,
            'expire',
            new \DateTimeImmutable('2026-11-02 03:00:00'),
            $this->context
        );
    }

    public function testOpensTheAccountWhenTheCustomerHasNone(): void
    {
        $calls = 0;
        $this->rowResolver = function () use (&$calls): mixed {
            ++$calls;

            return $calls === 1 ? false : $this->row(pointsBalance: 0, tierPoints: 0);
        };

        $this->ledger->bookPoints(self::CUSTOMER_ID, 'award', 10, $this->context);

        $account = $this->loyaltyAccountRepository->creates[0][0];
        static::assertSame(self::CUSTOMER_ID, $account['customerId']);
        static::assertSame(0, $account['pointsBalance']);
        static::assertSame(10, $this->pointTransactionRepository->creates[0][0]['balanceAfter']);
    }

    public function testAddsTierPoints(): void
    {
        $tierPoints = $this->ledger->addTierPoints(self::CUSTOMER_ID, 250, $this->context);

        static::assertSame(750, $tierPoints);
        static::assertSame(750, $this->loyaltyAccountRepository->updates[0][0]['tierPoints']);
    }

    /**
     * A return gives back into the batch the points left, keeping its date: the withdrawal records
     * what came back and the batch grows by exactly that.
     */
    public function testAGiveBackPutsThePointsBackIntoTheBatchTheyLeft(): void
    {
        $this->openUses = [[
            'useId' => self::FIRST_LOT_ID,
            'lotId' => self::SECOND_LOT_ID,
            'points' => 100,
            'returned' => 0,
            'remaining' => 20,
            'expiresAt' => new \DateTimeImmutable('2027-01-01 00:00:00'),
            'expiredAt' => null,
        ]];

        $given = $this->ledger->giveBackPoints(self::CUSTOMER_ID, 'return', self::ACCOUNT_ID, 200, $this->context, 'RT-1');

        static::assertSame(100, $given);

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame('return', $transaction['type']);
        static::assertSame(100, $transaction['points']);
        static::assertSame(300, $transaction['balanceAfter']);
        static::assertSame('RT-1', $transaction['comment']);

        static::assertSame(100, $this->useRepository->updates[0][0]['returnedPoints']);
        static::assertSame(120, $this->lotRepository->updates[0][0]['remainingPoints']);
        static::assertSame(300, $this->loyaltyAccountRepository->updates[0][0]['pointsBalance']);
    }

    public function testAGiveBackWithNothingOutWritesNoBooking(): void
    {
        $this->openUses = [];

        static::assertSame(0, $this->ledger->giveBackPoints(self::CUSTOMER_ID, 'return', self::ACCOUNT_ID, 100, $this->context));
        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->loyaltyAccountRepository->updates);
        static::assertSame([], $this->lotRepository->updates);
    }

    /**
     * Taking back what an order earned walks the batches of that order - not the ones that expire
     * next, which may belong to a different order entirely.
     */
    public function testRevokingTakesFromTheBatchesOfTheOrder(): void
    {
        $this->openLots = [
            ['id' => self::FIRST_LOT_ID, 'remaining' => 100],
            ['id' => self::SECOND_LOT_ID, 'remaining' => 100],
        ];

        static::assertSame(150, $this->ledger->revokeOrderPoints(self::CUSTOMER_ID, 'revoke', self::ACCOUNT_ID, 150, $this->context, 'RT-1'));

        $transaction = $this->pointTransactionRepository->creates[0][0];
        static::assertSame('revoke', $transaction['type']);
        static::assertSame(-150, $transaction['points']);
        static::assertSame(50, $transaction['balanceAfter']);

        $uses = $this->useRepository->creates[0];
        static::assertCount(2, $uses);
        static::assertSame(0, $uses[0]['returnedPoints']);
        static::assertSame(0, $this->lotRepository->updates[0][0]['remainingPoints']);
        static::assertSame(50, $this->lotRepository->updates[0][1]['remainingPoints']);
    }

    public function testRevokingWhatIsAlreadySpentWritesNoBooking(): void
    {
        $this->openLots = [];

        static::assertSame(0, $this->ledger->revokeOrderPoints(self::CUSTOMER_ID, 'revoke', self::ACCOUNT_ID, 100, $this->context));
        static::assertSame([], $this->pointTransactionRepository->creates);
        static::assertSame([], $this->loyaltyAccountRepository->updates);
    }

    /**
     * A locked row as the database answers it: uuid columns in bytes, everything else as stored.
     *
     * @return array<string, mixed>
     */
    private function row(
        int $pointsBalance = 200,
        int $tierPoints = 500,
        ?string $id = null,
    ): array {
        return [
            'id' => Uuid::fromHexToBytes($id ?? self::ACCOUNT_ID),
            'points_balance' => $pointsBalance,
            'tier_points' => $tierPoints,
        ];
    }
}
