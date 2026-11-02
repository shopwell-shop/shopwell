<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1793604700CreateLoyaltyRedemptionLevelTable;

/**
 * The table is the ladder itself, so what is asserted here is the shape the calculator relies on: a
 * unique point count to read the steps by and nothing else - no ranges, no channel, no flag - plus the
 * fact that a fresh installation starts with an empty ladder instead of a guessed default.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793604700CreateLoyaltyRedemptionLevelTable::class)]
class Migration1793604700CreateLoyaltyRedemptionLevelTableTest extends TestCase
{
    private const TABLE = 'loyalty_redemption_level';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793604700, (new Migration1793604700CreateLoyaltyRedemptionLevelTable())->getCreationTimestamp());
    }

    public function testTheMigrationCreatesTheTableAndIsIdempotent(): void
    {
        $migration = new Migration1793604700CreateLoyaltyRedemptionLevelTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, self::TABLE));
    }

    public function testTheColumnsAreWhatTheCalculatorReads(): void
    {
        $this->recreateTable();

        static::assertSame(
            ['id', 'points', 'value_per_point', 'created_at', 'updated_at'],
            array_column(TableHelper::getTable($this->connection, self::TABLE)->columns, 'name')
        );

        $points = TableHelper::getColumnOfTable($this->connection, self::TABLE, 'points');
        static::assertTrue($points->isNotNull);
        static::assertSame('integer', $points->type);

        $valuePerPoint = TableHelper::getColumnOfTable($this->connection, self::TABLE, 'value_per_point');
        static::assertTrue($valuePerPoint->isNotNull);
        static::assertSame('float', $valuePerPoint->type);

        // a step never carries a default rate: a row that does not say what a point is worth is a row
        // somebody has to look at, not one the database quietly completes
        static::assertNull($valuePerPoint->defaultValue);
    }

    /**
     * Two rows with the same point count would mean two rates for one band, and the winner would be
     * whichever the database returned first. The unique key makes that unrepresentable.
     */
    public function testAPointCountCanOnlyBeConfiguredOnce(): void
    {
        $this->recreateTable();

        static::assertTrue(TableHelper::indexExists($this->connection, self::TABLE, 'uniq.loyalty_redemption_level.points'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            self::TABLE,
            'uniq.loyalty_redemption_level.points',
            ['points']
        ));
    }

    /**
     * The ladder is additive: an installation without steps keeps the shop wide rate, which is what the
     * shop did before this table existed. Seeding a step here would change the price of every point on
     * every upgrade.
     */
    public function testAFreshInstallationStartsWithAnEmptyLadder(): void
    {
        $this->recreateTable();

        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM `' . self::TABLE . '`'));
    }

    private function recreateTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        (new Migration1793604700CreateLoyaltyRedemptionLevelTable())->update($this->connection);
    }
}
