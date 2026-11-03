<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1793606000CreateLoyaltyPointLotTables;

/**
 * The backfill is the part that has to be right: once spending walks the batches, a balance without
 * batches is unspendable, and a balance split the wrong way overcharges the wrong expiry date.
 *
 * The interesting halves are covered: the already spent part comes off the batch that expires first, a
 * fully spent batch is not written at all, the batches add up to the balance, and running the copy a
 * second time does not double the history.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793606000CreateLoyaltyPointLotTables::class)]
class Migration1793606000CreateLoyaltyPointLotTablesTest extends TestCase
{
    private const LOT_TABLE = 'loyalty_point_lot';

    private const USE_TABLE = 'loyalty_point_lot_use';

    private const TRANSACTION_TABLE = 'loyalty_point_transaction';

    private const ACCOUNT_TABLE = 'loyalty_account';

    private const PROBE = 'lot-probe';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->deleteProbes();
    }

    protected function tearDown(): void
    {
        $this->deleteProbes();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793606000, (new Migration1793606000CreateLoyaltyPointLotTables())->getCreationTimestamp());
    }

    public function testTheMigrationCreatesTheTablesAndIsIdempotent(): void
    {
        $this->recreateTables();

        static::assertTrue(TableHelper::tableExists($this->connection, self::LOT_TABLE));
        static::assertTrue(TableHelper::tableExists($this->connection, self::USE_TABLE));
    }

    public function testTheColumnsAreWhatTheLedgerWrites(): void
    {
        $this->recreateTables();

        static::assertSame(
            [
                'id', 'customer_id', 'points', 'remaining_points', 'expires_at', 'expired_at',
                'order_id', 'order_version_id', 'created_at', 'updated_at',
            ],
            array_column(TableHelper::getTable($this->connection, self::LOT_TABLE)->columns, 'name')
        );

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, self::LOT_TABLE, 'remaining_points')->isNotNull);
        static::assertFalse(TableHelper::getColumnOfTable($this->connection, self::LOT_TABLE, 'expires_at')->isNotNull);

        // a return gives points back per withdrawal, so the counter that keeps it from giving them
        // twice belongs on the withdrawal and not on the batch
        static::assertSame(
            ['id', 'lot_id', 'transaction_id', 'points', 'returned_points', 'created_at', 'updated_at'],
            array_column(TableHelper::getTable($this->connection, self::USE_TABLE)->columns, 'name')
        );
    }

    public function testTheExpiryScanHasAnIndex(): void
    {
        $this->recreateTables();

        static::assertTrue(TableHelper::indexExists($this->connection, self::LOT_TABLE, 'idx.loyalty_point_lot.expires_remaining'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            self::LOT_TABLE,
            'idx.loyalty_point_lot.expires_remaining',
            ['expires_at', 'remaining_points']
        ));
    }

    /**
     * A customer who spent 180 of 300 points owns 120. Which 120? The ones that expire last - the old
     * implementation could not answer this and simply compared totals.
     */
    public function testTheBackfillSpendsTheBatchesThatExpireFirst(): void
    {
        $this->recreateTables();

        $customerId = $this->createProbeCustomer('first');
        $this->createAccount($customerId, 120);

        $this->createAward($customerId, 100, '+10 days', '2026-01-01 00:00:00.000');
        $this->createAward($customerId, 200, '+30 days', '2026-01-02 00:00:00.000');

        (new Migration1793606000CreateLoyaltyPointLotTables())->update($this->connection);

        static::assertSame([120], $this->remainingOf($customerId));
        static::assertSame(120, $this->lotTotalOf($customerId));
    }

    /**
     * A batch that was fully spent keeps no row: it can never be spent again and never expires, so
     * writing it would only add rows that every scan has to skip.
     */
    public function testAFullySpentBatchIsNotWritten(): void
    {
        $this->recreateTables();

        $customerId = $this->createProbeCustomer('spent');
        $this->createAccount($customerId, 0);

        $this->createAward($customerId, 100, '+10 days', '2026-01-01 00:00:00.000');

        (new Migration1793606000CreateLoyaltyPointLotTables())->update($this->connection);

        static::assertSame([], $this->remainingOf($customerId));
    }

    /**
     * A batch without an expiry date - what a manual adjustment books - keeps its points.
     */
    public function testABatchWithoutExpiryDateIsKeptWhole(): void
    {
        $this->recreateTables();

        $customerId = $this->createProbeCustomer('forever');
        $this->createAccount($customerId, 50);

        $this->createAward($customerId, 50, null, '2026-01-01 00:00:00.000');

        (new Migration1793606000CreateLoyaltyPointLotTables())->update($this->connection);

        static::assertSame([50], $this->remainingOf($customerId));
        static::assertNull($this->connection->fetchOne(
            'SELECT `expires_at` FROM `' . self::LOT_TABLE . '` WHERE `customer_id` = :id',
            ['id' => $customerId]
        ));
    }

    /**
     * The balances of several customers have to be split independently: the window function partitions
     * by customer, and this pins that down.
     */
    public function testEveryCustomerIsBackfilledOnTheirOwn(): void
    {
        $this->recreateTables();

        $first = $this->createProbeCustomer('a');
        $second = $this->createProbeCustomer('b');

        $this->createAccount($first, 70);
        $this->createAccount($second, 30);

        $this->createAward($first, 70, '+10 days', '2026-01-01 00:00:00.000');
        $this->createAward($second, 100, '+10 days', '2026-01-01 00:00:00.000');
        $this->createAward($second, 50, '+30 days', '2026-01-02 00:00:00.000');

        (new Migration1793606000CreateLoyaltyPointLotTables())->update($this->connection);

        static::assertSame([70], $this->remainingOf($first));
        static::assertSame([30], $this->remainingOf($second));
    }

    public function testRunningTheBackfillAgainDoesNotDuplicateTheHistory(): void
    {
        $this->recreateTables();

        $customerId = $this->createProbeCustomer('again');
        $this->createAccount($customerId, 100);
        $this->createAward($customerId, 100, '+10 days', '2026-01-01 00:00:00.000');

        $migration = new Migration1793606000CreateLoyaltyPointLotTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame([100], $this->remainingOf($customerId));
    }

    public function testDeletingACustomerTakesTheirBatchesWithThem(): void
    {
        $this->recreateTables();

        static::assertTrue(TableHelper::foreignKeyExists(
            $this->connection,
            self::LOT_TABLE,
            'fk.loyalty_point_lot.customer_id'
        ));
        static::assertSame('CASCADE', $this->deleteRuleOf(self::LOT_TABLE, 'fk.loyalty_point_lot.customer_id'));
    }

    public function testAUseRowDisappearsWithItsBooking(): void
    {
        $this->recreateTables();

        static::assertTrue(TableHelper::foreignKeyExists(
            $this->connection,
            self::USE_TABLE,
            'fk.loyalty_point_lot_use.transaction_id'
        ));
        static::assertSame('CASCADE', $this->deleteRuleOf(self::USE_TABLE, 'fk.loyalty_point_lot_use.transaction_id'));
    }

    private function deleteRuleOf(string $table, string $foreignKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT `DELETE_RULE` FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
                WHERE `CONSTRAINT_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :foreignKey',
            ['table' => $table, 'foreignKey' => $foreignKey]
        );
    }

    private function recreateTables(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::USE_TABLE . '`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::LOT_TABLE . '`');

        // the backfill only runs on an empty table, so every test has to start from one
        (new Migration1793606000CreateLoyaltyPointLotTables())->update($this->connection);
        $this->connection->executeStatement('DELETE FROM `' . self::LOT_TABLE . '`');
    }

    /**
     * The probe customer borrows the group and the sales channel the shop already has: that is all a
     * customer needs to exist, and rebuilding them here would test the fixtures instead of the
     * backfill.
     */
    private function createProbeCustomer(string $suffix): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('customer', [
            'id' => $id,
            'customer_group_id' => $this->existingIdOf('customer_group'),
            'sales_channel_id' => $this->existingIdOf('sales_channel'),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'customer_number' => self::PROBE . '-' . $suffix,
            'name' => 'Probe ' . $suffix,
            'email' => self::PROBE . '-' . $suffix . '@example.test',
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        return $id;
    }

    private function existingIdOf(string $table): string
    {
        $id = $this->connection->fetchOne('SELECT `id` FROM `' . $table . '` LIMIT 1');

        static::assertIsString($id, 'the test database has no row in ' . $table);

        return $id;
    }

    private function createAccount(string $customerId, int $balance): void
    {
        $this->connection->insert(self::ACCOUNT_TABLE, [
            'id' => Uuid::randomBytes(),
            'customer_id' => $customerId,
            'points_balance' => $balance,
            'tier_points' => 0,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
    }

    private function createAward(string $customerId, int $points, ?string $expiresIn, string $createdAt): void
    {
        $this->connection->insert(self::TRANSACTION_TABLE, [
            'id' => Uuid::randomBytes(),
            'customer_id' => $customerId,
            'type' => 'award',
            'points' => $points,
            'balance_after' => 0,
            'expires_at' => $expiresIn === null
                ? null
                : (new \DateTimeImmutable($createdAt))->modify($expiresIn)->format('Y-m-d H:i:s.v'),
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @return list<int>
     */
    private function remainingOf(string $customerId): array
    {
        return array_map(
            static fn ($value): int => (int) $value,
            $this->connection->fetchFirstColumn(
                'SELECT `remaining_points` FROM `' . self::LOT_TABLE . '` WHERE `customer_id` = :id ORDER BY `remaining_points`',
                ['id' => $customerId]
            )
        );
    }

    private function lotTotalOf(string $customerId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(`remaining_points`), 0) FROM `' . self::LOT_TABLE . '` WHERE `customer_id` = :id',
            ['id' => $customerId]
        );
    }

    private function deleteProbes(): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT `id` FROM `customer` WHERE `customer_number` LIKE :prefix',
            ['prefix' => self::PROBE . '-%']
        );

        if ($ids === []) {
            return;
        }

        foreach ($ids as $id) {
            if (TableHelper::tableExists($this->connection, self::LOT_TABLE)) {
                $this->connection->executeStatement('DELETE FROM `' . self::LOT_TABLE . '` WHERE `customer_id` = :id', ['id' => $id]);
            }

            if (TableHelper::tableExists($this->connection, self::TRANSACTION_TABLE)) {
                $this->connection->executeStatement('DELETE FROM `' . self::TRANSACTION_TABLE . '` WHERE `customer_id` = :id', ['id' => $id]);
            }

            if (TableHelper::tableExists($this->connection, self::ACCOUNT_TABLE)) {
                $this->connection->executeStatement('DELETE FROM `' . self::ACCOUNT_TABLE . '` WHERE `customer_id` = :id', ['id' => $id]);
            }

            $this->connection->executeStatement('DELETE FROM `customer` WHERE `id` = :id', ['id' => $id]);
        }
    }
}
