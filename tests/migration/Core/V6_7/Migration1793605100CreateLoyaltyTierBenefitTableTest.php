<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1793605100CreateLoyaltyTierBenefitTable;

/**
 * Two things matter here: the shape the resolver reads, and the one-time copy of the three columns into
 * rows. The copy is what keeps a shop that configured a multiplier working after the upgrade, so the
 * tests cover the interesting halves of it - a configured value becomes a row, a default value does not,
 * and running the migration again does not double what is already there.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793605100CreateLoyaltyTierBenefitTable::class)]
class Migration1793605100CreateLoyaltyTierBenefitTableTest extends TestCase
{
    private const TABLE = 'loyalty_tier_benefit';

    private const TIER_TABLE = 'loyalty_tier';

    private const PROBE = 'benefit-probe';

    private Connection $connection;

    private string $tierId;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->tierId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->deleteProbe();
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

    public function testItCopiesTheConfiguredColumnsIntoRows(): void
    {
        $this->recreateTable();
        $this->createProbeTier(2.0, 5.0, true);

        (new Migration1793605100CreateLoyaltyTierBenefitTable())->update($this->connection);

        static::assertSame(
            [
                'cart_discount' => 5.0,
                'earn_multiplier' => 2.0,
                'free_shipping' => 1.0,
            ],
            $this->copiedBenefits()
        );
    }

    /**
     * A multiplier of 1 and a discount of 0 grant nothing. Writing them as rows would fill the table with
     * entries that mean "no benefit", and every reader would have to know that 1.0 is a no-op.
     */
    public function testItDoesNotCopyTheNeutralValues(): void
    {
        $this->recreateTable();
        $this->createProbeTier(1.0, 0.0, false);

        (new Migration1793605100CreateLoyaltyTierBenefitTable())->update($this->connection);

        static::assertSame([], $this->copiedBenefits());
    }

    public function testRunningTheCopyAgainDoesNotDuplicateRows(): void
    {
        $this->recreateTable();
        $this->createProbeTier(2.0, 5.0, true);

        $migration = new Migration1793605100CreateLoyaltyTierBenefitTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertCount(3, $this->copiedBenefits());
    }

    private function recreateTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        (new Migration1793605100CreateLoyaltyTierBenefitTable())->update($this->connection);
    }

    private function createProbeTier(float $multiplier, float $discount, bool $freeShipping): void
    {
        $this->deleteProbe();

        $this->connection->insert(self::TIER_TABLE, [
            'id' => $this->tierId,
            'technical_name' => self::PROBE,
            'level' => 99,
            'min_tier_points' => 0,
            'points_multiplier' => $multiplier,
            'discount_percent' => $discount,
            'free_shipping' => $freeShipping ? 1 : 0,
            'active' => 1,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);
    }

    private function deleteProbe(): void
    {
        if (TableHelper::tableExists($this->connection, self::TABLE)) {
            $this->connection->executeStatement(
                'DELETE FROM `' . self::TABLE . '` WHERE `loyalty_tier_id` = :id',
                ['id' => $this->tierId]
            );
        }

        $this->connection->executeStatement(
            'DELETE FROM `' . self::TIER_TABLE . '` WHERE `technical_name` = :name',
            ['name' => self::PROBE]
        );
    }

    /**
     * @return array<string, float> type => value of the probe tier, ordered by type for a stable assertion
     */
    private function copiedBenefits(): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT `type`, `value` FROM `' . self::TABLE . '` WHERE `loyalty_tier_id` = :id ORDER BY `type`',
            ['id' => $this->tierId]
        );

        return array_map(static fn ($value): float => (float) $value, $rows);
    }
}
