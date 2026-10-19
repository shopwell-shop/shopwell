<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1792700010CreatePaymentTransferTables;
use Shopwell\Core\Migration\V6_7\Migration1792700040AddTransferChannelParams;
use Shopwell\Core\Migration\V6_7\Migration1792700050AddTransferToPaymentNotification;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * The parameters a payout is built from have to survive the call that created it: the submission may
 * happen days later in a task that has no caller to ask, and the alipay adapter refuses a payout whose
 * scene is gone. The column is therefore part of the table of a fresh installation - and the migration
 * that adds it has to bring it to an installation that already has the table.
 *
 * The table is rebuilt from the migration that creates it instead of relying on the kernel bootstrap:
 * the bootstrap applies the whole chain, which would test the environment instead of this migration,
 * and the migration tests of this directory rebuild the table among themselves.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792700040AddTransferChannelParams::class)]
class Migration1792700040AddTransferChannelParamsTest extends TestCase
{
    private const TABLE = 'payment_transfer';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792700040, (new Migration1792700040AddTransferChannelParams())->getCreationTimestamp());
    }

    /**
     * A JSON column without a default, right after the currency it is sent with: the parameters are
     * input of the submission, not something the platform derives later.
     */
    public function testTheColumnIsNullableJsonAfterTheCurrency(): void
    {
        $this->rebuildTable();

        $column = $this->column();

        static::assertSame('json', $column['COLUMN_TYPE']);
        static::assertSame('YES', $column['IS_NULLABLE']);
        static::assertNull($column['COLUMN_DEFAULT'], 'the writer spells the value out, the ddl must not default it');
        static::assertSame($this->positionOf('currency') + 1, (int) $column['ORDINAL_POSITION']);
    }

    /**
     * The `hasColumn` guard exists because an installation may have got the column by another route -
     * a failed upgrade that was resumed, or a hand-fixed schema. A second run must be a no-op and not
     * append the column twice.
     */
    public function testRunningItTwiceLeavesASingleColumn(): void
    {
        $this->rebuildTable();

        (new Migration1792700040AddTransferChannelParams())->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `COLUMN_NAME` = :column',
            ['table' => self::TABLE, 'column' => 'channel_params']
        ));
    }

    /**
     * The column is nullable without a default, and a nullable column the dal leaves out of an insert
     * is the case that has bitten this module before (`INT NOT NULL` without a default, see
     * `PaymentTransferService::create()`): a row written without the parameters has to be writable and
     * readable, with the value coming back as null and not as an error or an empty document.
     */
    public function testATransferWithoutChannelParametersStaysWritable(): void
    {
        $this->rebuildTable();

        $appId = Uuid::randomBytes();
        $recipientId = Uuid::randomBytes();
        $transferId = Uuid::randomBytes();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.v');

        $this->connection->insert('payment_app', [
            'id' => $appId,
            // unique per run: a failed assertion leaves the row behind for the next run
            'technical_name' => 'migration-test-app-' . bin2hex(random_bytes(4)),
            'name' => 'Migration Test',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'app_secret' => 'secret',
            'created_at' => $now,
        ]);

        $this->connection->insert('payment_transfer_recipient', [
            'id' => $recipientId,
            'payment_app_id' => $appId,
            'provider' => 'alipay',
            'identity_type' => 'alipay_user_id',
            'identity_value' => 'ciphertext',
            'onboarding_status' => PaymentTransferStates::ONBOARDING_REGISTERED,
            'payout_enabled' => 1,
            'created_at' => $now,
        ]);

        $this->connection->insert(self::TABLE, [
            'id' => $transferId,
            'payment_app_id' => $appId,
            'transfer_no' => 'PT261018090000ABCDEF0123',
            'out_no' => 'commission0001',
            'recipient_id' => $recipientId,
            'provider' => 'alipay',
            'channel' => 'alipay_h5',
            'amount' => 100,
            'currency' => 'CNY',
            'status_id' => $this->createdStateId(),
            'submit_attempts' => 0,
            'created_at' => $now,
        ]);

        static::assertNull($this->connection->fetchOne(
            'SELECT `channel_params` FROM `payment_transfer` WHERE `id` = :id',
            ['id' => $transferId]
        ));

        // and the row is complete - a column that silently swallowed the insert would still answer null
        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `payment_transfer` WHERE `id` = :id AND `out_no` = :outNo',
            ['id' => $transferId, 'outNo' => 'commission0001']
        ));

        $this->connection->executeStatement('DELETE FROM `payment_transfer` WHERE `id` = :id', ['id' => $transferId]);
        $this->connection->executeStatement('DELETE FROM `payment_transfer_recipient` WHERE `id` = :id', ['id' => $recipientId]);
        $this->connection->executeStatement('DELETE FROM `payment_app` WHERE `id` = :id', ['id' => $appId]);
    }

    /**
     * Drops both transfer tables and rebuilds the shape an installation has before this migration: the
     * table of the creating migration, without the parameters.
     *
     * The transitions of this machine are reset first for the same reason the neighbouring migration
     * tests do it - the importer is add-only, and the kernel bootstrap has already imported them once.
     * Only this machine is touched, the other machines of the database must stay as they are.
     */
    private function rebuildTable(): void
    {
        // the notification table points at the transfer table, and MySQL does not drop a table an
        // existing constraint still names: the constraint goes first and the migration that adds it
        // is replayed after the tables are back
        if (TableHelper::foreignKeyExists($this->connection, 'payment_notification', 'fk.payment_notification.payment_transfer_id')) {
            $this->connection->executeStatement('ALTER TABLE `payment_notification` DROP FOREIGN KEY `fk.payment_notification.payment_transfer_id`');
        }

        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_transfer`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_transfer_recipient`');

        $this->connection->executeStatement(
            'DELETE FROM `state_machine_transition` WHERE `state_machine_id` = (
                SELECT `id` FROM `state_machine` WHERE `technical_name` = :machine
            )',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );

        (new Migration1792700010CreatePaymentTransferTables())->update($this->connection);

        static::assertSame([], $this->columnList('channel_params'), 'the creating migration must not already bring the column this one adds');

        (new Migration1792700040AddTransferChannelParams())->update($this->connection);
        (new Migration1792700050AddTransferToPaymentNotification())->update($this->connection);
    }

    /**
     * @return array{COLUMN_TYPE: string, IS_NULLABLE: string, COLUMN_DEFAULT: string|null, ORDINAL_POSITION: int|string}
     */
    private function column(): array
    {
        $columns = $this->columnList('channel_params');

        static::assertCount(1, $columns, 'the column "channel_params" was not added');

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

    private function createdStateId(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT `id` FROM `state_machine_state` WHERE `state_machine_id` = (
                SELECT `id` FROM `state_machine` WHERE `technical_name` = :machine
            ) AND `technical_name` = :state',
            ['machine' => PaymentTransferStates::STATE_MACHINE, 'state' => PaymentTransferStates::STATE_CREATED]
        );

        static::assertIsString($id, 'the creating migration has to import the state machine');

        return $id;
    }
}
