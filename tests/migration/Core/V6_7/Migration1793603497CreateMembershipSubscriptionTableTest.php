<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1793603497CreateMembershipSubscriptionTable;

/**
 * A membership is a row that changes: it gets extended, replaced or ended, and the daily task finds it
 * by its end. The shape has to carry all three, which is what is asserted here - the end is indexed and
 * nullable-free, the reason for an end is a column of its own, and a membership granted without a
 * purchase is allowed to have no price.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793603497CreateMembershipSubscriptionTable::class)]
class Migration1793603497CreateMembershipSubscriptionTableTest extends TestCase
{
    private const TABLE = 'membership_subscription';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793603497, (new Migration1793603497CreateMembershipSubscriptionTable())->getCreationTimestamp());
    }

    public function testTheMigrationCreatesTheTableAndIsIdempotent(): void
    {
        $migration = new Migration1793603497CreateMembershipSubscriptionTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, self::TABLE));
    }

    public function testTheColumnsAreWhatTheStateMachineWrites(): void
    {
        $this->recreateTable();

        static::assertSame(
            [
                'id', 'customer_id', 'membership_plan_id', 'loyalty_tier_id', 'plan_name', 'amount', 'currency',
                'state', 'starts_at', 'ends_at', 'ended_at', 'end_reason', 'last_purchase_id', 'created_at',
                'updated_at',
            ],
            array_column(TableHelper::getTable($this->connection, self::TABLE)->columns, 'name')
        );

        foreach (['customer_id', 'state', 'starts_at', 'ends_at'] as $column) {
            static::assertTrue(
                TableHelper::getColumnOfTable($this->connection, self::TABLE, $column)->isNotNull,
                $column . ' is what "does this membership run" is answered with and must never be null'
            );
        }

        // ending is an action somebody takes, so everything about it is written at that moment and not
        // at creation
        foreach (['ended_at', 'end_reason'] as $column) {
            static::assertFalse(TableHelper::getColumnOfTable($this->connection, self::TABLE, $column)->isNotNull);
        }
    }

    /**
     * A membership granted by an operator has no price and no purchase behind it. Forcing a currency
     * would mean inventing one, which turns "no amount" into a wrong amount.
     */
    public function testAMembershipWithoutAPurchaseMayCarryNoPrice(): void
    {
        $this->recreateTable();

        foreach (['plan_name', 'currency', 'last_purchase_id', 'membership_plan_id', 'loyalty_tier_id'] as $column) {
            static::assertFalse(
                TableHelper::getColumnOfTable($this->connection, self::TABLE, $column)->isNotNull,
                $column . ' must stay nullable'
            );
        }

        $amount = TableHelper::getColumnOfTable($this->connection, self::TABLE, 'amount');
        static::assertTrue($amount->isNotNull);
        static::assertSame('0', $amount->defaultValue);
    }

    /**
     * The expiration task looks for exactly the memberships whose period ran out, so `ends_at` is
     * indexed next to the state it filters by.
     */
    public function testTheExpirationTaskHasItsIndexes(): void
    {
        $this->recreateTable();

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, self::TABLE, 'idx.membership_subscription.state', ['state']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, self::TABLE, 'idx.membership_subscription.ends_at', ['ends_at']));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, self::TABLE, 'idx.membership_subscription.customer_id', ['customer_id']));
    }

    /**
     * A customer that is deleted takes the membership with it; a deleted plan, tier or order must not
     * delete the membership, because the customer still holds it - only the reference goes.
     */
    public function testTheCustomerCascadesAndTheReferencesOnlyClear(): void
    {
        $this->recreateTable();

        $references = [
            ['fk.membership_subscription.customer_id', 'customer', 'CASCADE'],
            ['fk.membership_subscription.membership_plan_id', 'membership_plan', 'SET NULL'],
            ['fk.membership_subscription.loyalty_tier_id', 'loyalty_tier', 'SET NULL'],
            ['fk.membership_subscription.last_purchase_id', 'membership_purchase', 'SET NULL'],
        ];

        foreach ($references as [$foreignKey, $referencedTable, $deleteRule]) {
            static::assertTrue(TableHelper::foreignKeyExists($this->connection, self::TABLE, $foreignKey), $foreignKey . ' was not created');
            static::assertSame($referencedTable, $this->referencedTableOf($foreignKey), $foreignKey . ' points at the wrong table');
            static::assertSame($deleteRule, $this->deleteRuleOf($foreignKey), $foreignKey . ' carries the wrong delete rule');
        }
    }

    private function recreateTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        (new Migration1793603497CreateMembershipSubscriptionTable())->update($this->connection);
    }

    private function deleteRuleOf(string $foreignKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT `DELETE_RULE` FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
                WHERE `CONSTRAINT_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :foreignKey',
            ['table' => self::TABLE, 'foreignKey' => $foreignKey]
        );
    }

    private function referencedTableOf(string $foreignKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT `REFERENCED_TABLE_NAME` FROM `information_schema`.`KEY_COLUMN_USAGE`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :foreignKey
                LIMIT 1',
            ['table' => self::TABLE, 'foreignKey' => $foreignKey]
        );
    }
}
