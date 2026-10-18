<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1792700050AddTransferToPaymentNotification;

/**
 * A payout notification is stored in the very same table as a payment notification, and the column
 * that says which of the two it belongs to has to be added to an installation that already has the
 * table.
 *
 * The column is nullable because a notification is written before it is looked at: the payout it is
 * about is only known after the signature was checked and the body read. A `NOT NULL` column without
 * a default would make every notification unwritable.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792700050AddTransferToPaymentNotification::class)]
class Migration1792700050AddTransferToPaymentNotificationTest extends TestCase
{
    private const TABLE = 'payment_notification';

    private const COLUMN = 'payment_transfer_id';

    private const INDEX = 'idx.payment_notification.payment_transfer_id';

    private const FOREIGN_KEY = 'fk.payment_notification.payment_transfer_id';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792700050, (new Migration1792700050AddTransferToPaymentNotification())->getCreationTimestamp());
    }

    /**
     * The reference is stored the way every other id of this dal is stored, and it sits next to the
     * order reference it is the alternative to.
     */
    public function testTheColumnIsANullableReferenceAfterThePaymentOrder(): void
    {
        $this->rebuild();

        $column = $this->column();

        static::assertSame('binary(16)', $column['COLUMN_TYPE']);
        static::assertSame('YES', $column['IS_NULLABLE']);
        static::assertNull($column['COLUMN_DEFAULT'], 'the writer spells the value out, the ddl must not default it');
        static::assertSame($this->positionOf('payment_order_id') + 1, (int) $column['ORDINAL_POSITION']);
    }

    /**
     * The constraint is what keeps a notification from outliving the payout it belongs to, and it has
     * to behave like the order reference next to it.
     */
    public function testTheReferenceCascadesWithTheTransfer(): void
    {
        $this->rebuild();

        $constraint = $this->connection->fetchAssociative(
            'SELECT `DELETE_RULE`, `UPDATE_RULE`
                FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
                WHERE `CONSTRAINT_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :constraint',
            ['table' => self::TABLE, 'constraint' => self::FOREIGN_KEY]
        );

        static::assertIsArray($constraint, 'the foreign key was not added');
        static::assertSame('CASCADE', $constraint['DELETE_RULE']);
        static::assertSame('CASCADE', $constraint['UPDATE_RULE']);

        $reference = $this->connection->fetchAssociative(
            'SELECT `REFERENCED_TABLE_NAME`, `REFERENCED_COLUMN_NAME`
                FROM `information_schema`.`KEY_COLUMN_USAGE`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :constraint',
            ['table' => self::TABLE, 'constraint' => self::FOREIGN_KEY]
        );

        static::assertIsArray($reference);
        static::assertSame('payment_transfer', $reference['REFERENCED_TABLE_NAME']);
        static::assertSame('id', $reference['REFERENCED_COLUMN_NAME']);
    }

    /**
     * The column and the constraint are guarded apart: the migration tests of the transfer tables drop
     * the constraint, rebuild `payment_transfer` and replay this migration. A guard that only looked at
     * the column would leave those runs with a column and no constraint.
     */
    public function testRunningItTwiceLeavesASingleColumnAndASingleConstraint(): void
    {
        $this->rebuild();

        (new Migration1792700050AddTransferToPaymentNotification())->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `COLUMN_NAME` = :column',
            ['table' => self::TABLE, 'column' => self::COLUMN]
        ));

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
                WHERE `CONSTRAINT_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :constraint',
            ['table' => self::TABLE, 'constraint' => self::FOREIGN_KEY]
        ));
    }

    /**
     * Every row written before this migration exists without a payout, and every row is written before
     * the payout is known - so a notification without the reference has to stay writable and read back
     * as null instead of failing the insert.
     */
    public function testANotificationWithoutATransferStaysWritable(): void
    {
        $this->rebuild();

        $notificationId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        $this->connection->insert(self::TABLE, [
            'id' => $notificationId,
            'provider_technical_name' => 'alipay',
            'raw_body' => 'out_biz_no=PO-1',
            'created_at' => $now,
        ]);

        static::assertNull($this->connection->fetchOne(
            'SELECT `payment_transfer_id` FROM `payment_notification` WHERE `id` = :id',
            ['id' => $notificationId]
        ));

        // and the row is complete - a column that silently swallowed the insert would still answer null
        static::assertSame('alipay', $this->connection->fetchOne(
            'SELECT `provider_technical_name` FROM `payment_notification` WHERE `id` = :id',
            ['id' => $notificationId]
        ));

        $this->connection->executeStatement('DELETE FROM `payment_notification` WHERE `id` = :id', ['id' => $notificationId]);
    }

    /**
     * Rebuilds the shape an installation has before this migration: the notification table without the
     * payout reference.
     *
     * The index is dropped together with the constraint although MySQL keeps it when the constraint
     * goes - otherwise the second run of the migration would find the index and never create it again,
     * and this test would prove a shape no installation has.
     */
    private function rebuild(): void
    {
        if (TableHelper::foreignKeyExists($this->connection, self::TABLE, self::FOREIGN_KEY)) {
            $this->connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '` DROP FOREIGN KEY `' . self::FOREIGN_KEY . '`'
            );
        }

        if (TableHelper::indexExists($this->connection, self::TABLE, self::INDEX)) {
            $this->connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '` DROP INDEX `' . self::INDEX . '`'
            );
        }

        if (TableHelper::columnExists($this->connection, self::TABLE, self::COLUMN)) {
            $this->connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '` DROP COLUMN `' . self::COLUMN . '`'
            );
        }

        static::assertSame([], $this->columnList(self::COLUMN), 'the test could not remove the column it wants to add');

        (new Migration1792700050AddTransferToPaymentNotification())->update($this->connection);
    }

    /**
     * @return array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: string|null, ORDINAL_POSITION: int|string}
     */
    private function column(): array
    {
        $columns = $this->columnList(self::COLUMN);

        static::assertCount(1, $columns, 'the column "' . self::COLUMN . '" was not added');

        return $columns[0];
    }

    /**
     * @return list<array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: string|null, ORDINAL_POSITION: int|string}>
     */
    private function columnList(string $name): array
    {
        /** @var list<array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: string|null, ORDINAL_POSITION: int|string}> $columns */
        $columns = $this->connection->fetchAllAssociative(
            'SELECT `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `ORDINAL_POSITION`
                FROM `information_schema`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `COLUMN_NAME` = :column
                ORDER BY `ORDINAL_POSITION`',
            ['table' => self::TABLE, 'column' => $name]
        );

        return $columns;
    }

    private function positionOf(string $name): int
    {
        $columns = $this->columnList($name);

        static::assertCount(1, $columns, 'the column "' . $name . '" does not exist');

        return (int) $columns[0]['ORDINAL_POSITION'];
    }
}
