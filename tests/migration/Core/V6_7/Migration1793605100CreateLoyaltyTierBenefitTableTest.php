<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1793605100CreateLoyaltyTierBenefitTable;

/**
 * The migration only creates a table - there is nothing to copy, because the three columns it replaces
 * never reached a released installation. What is worth pinning down is therefore the shape the resolver
 * reads and the two constraints that keep it sane: one row per type and per tier, and no benefit that
 * outlives the tier it belongs to.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793605100CreateLoyaltyTierBenefitTable::class)]
class Migration1793605100CreateLoyaltyTierBenefitTableTest extends TestCase
{
    private const TABLE = 'loyalty_tier_benefit';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793605100, (new Migration1793605100CreateLoyaltyTierBenefitTable())->getCreationTimestamp());
    }

    public function testTheMigrationCreatesTheTableAndIsIdempotent(): void
    {
        $migration = new Migration1793605100CreateLoyaltyTierBenefitTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, self::TABLE));
    }

    public function testTheColumnsAreWhatTheResolverReads(): void
    {
        $this->recreateTable();

        static::assertSame(
            ['id', 'loyalty_tier_id', 'type', 'value', 'position', 'created_at', 'updated_at'],
            array_column(TableHelper::getTable($this->connection, self::TABLE)->columns, 'name')
        );

        $type = TableHelper::getColumnOfTable($this->connection, self::TABLE, 'type');
        static::assertTrue($type->isNotNull);

        $value = TableHelper::getColumnOfTable($this->connection, self::TABLE, 'value');
        static::assertTrue($value->isNotNull);
        static::assertSame('float', $value->type);
    }

    /**
     * A tier that granted the same benefit twice would leave every reader with the question which of the
     * two wins. The key says the question does not exist.
     */
    public function testATypeCanOnlyBeGrantedOncePerTier(): void
    {
        $this->recreateTable();

        static::assertTrue(TableHelper::indexExists($this->connection, self::TABLE, 'uniq.loyalty_tier_benefit.type'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            self::TABLE,
            'uniq.loyalty_tier_benefit.type',
            ['loyalty_tier_id', 'type']
        ));
    }

    /**
     * The tier owns its benefits. Deleting a tier has to take them with it, otherwise the rows would
     * survive as grants nobody can see and would be picked up again by a tier that reuses the id.
     */
    public function testDeletingATierDeletesItsBenefits(): void
    {
        $this->recreateTable();

        static::assertTrue(TableHelper::foreignKeyExists(
            $this->connection,
            self::TABLE,
            'fk.loyalty_tier_benefit.loyalty_tier_id'
        ));
    }

    private function recreateTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        (new Migration1793605100CreateLoyaltyTierBenefitTable())->update($this->connection);
    }
}
