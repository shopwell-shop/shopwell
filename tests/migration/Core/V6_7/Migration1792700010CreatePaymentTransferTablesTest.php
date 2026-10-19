<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1792700010CreatePaymentTransferTables;
use Shopwell\Core\Migration\V6_7\Migration1792700020AddTransferRecipientIdentityHash;
use Shopwell\Core\Migration\V6_7\Migration1792700040AddTransferChannelParams;
use Shopwell\Core\Migration\V6_7\Migration1792700050AddTransferToPaymentNotification;
use Shopwell\Core\System\Payment\Transfer\PaymentTransferStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792700010CreatePaymentTransferTables::class)]
class Migration1792700010CreatePaymentTransferTablesTest extends TestCase
{
    private const TABLES = [
        'payment_transfer_recipient',
        'payment_transfer',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792700010, (new Migration1792700010CreatePaymentTransferTables())->getCreationTimestamp());
    }

    public function testMigrationCreatesBothTablesAndIsIdempotent(): void
    {
        $migration = new Migration1792700010CreatePaymentTransferTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), 'the table "' . $table . '" was not created');
        }
    }

    /**
     * The recipient carries the identity the interface pays out to, in whatever maturity the interface
     * requires: a bare identifier, an onboarded account or full bank details.
     */
    public function testPaymentTransferRecipientTable(): void
    {
        $this->recreateTables();

        static::assertSame(
            [
                'id', 'payment_app_id', 'provider', 'identity_type', 'identity_value', 'identity_hash',
                'holder_name', 'provider_account_id', 'bank_info', 'onboarding_status', 'payout_enabled',
                'verified_at', 'metadata', 'created_at', 'updated_at',
            ],
            $this->columnsOf('payment_transfer_recipient')
        );

        $id = TableHelper::getColumnOfTable($this->connection, 'payment_transfer_recipient', 'id');
        static::assertTrue($id->isNotNull);

        foreach (['provider', 'identity_type'] as $column) {
            $definition = TableHelper::getColumnOfTable($this->connection, 'payment_transfer_recipient', $column);
            static::assertTrue($definition->isNotNull);
            static::assertSame(64, $definition->length);
        }

        $identityValue = TableHelper::getColumnOfTable($this->connection, 'payment_transfer_recipient', 'identity_value');
        static::assertTrue($identityValue->isNotNull);
        static::assertSame(255, $identityValue->length);

        // the name of a holder is only required by some interfaces, the account id only exists once
        // the interface knows the recipient
        foreach (['holder_name', 'provider_account_id', 'verified_at', 'bank_info', 'metadata'] as $column) {
            static::assertFalse(
                TableHelper::getColumnOfTable($this->connection, 'payment_transfer_recipient', $column)->isNotNull,
                $column . ' must stay nullable'
            );
        }
    }

    /**
     * Both columns are written by whoever creates the row, so the ddl must not carry a default: the
     * dal never writes the property initial value, and any default would be permanent schema drift.
     * The one exception is the boolean - the dal fixes its default to 0 for every boolean column.
     */
    public function testTheRecipientColumnsTheWritersSpellOutCarryNoDefault(): void
    {
        $this->recreateTables();

        static::assertNull(
            TableHelper::getColumnOfTable($this->connection, 'payment_transfer_recipient', 'onboarding_status')->defaultValue,
            'onboarding_status is written explicitly by the creator'
        );

        static::assertSame(
            '0',
            TableHelper::getColumnOfTable($this->connection, 'payment_transfer_recipient', 'payout_enabled')->defaultValue,
            'the dal fixes the default of every boolean column to 0'
        );
    }

    /**
     * A recipient identity is unique per app and interface: the same alipay uid is a different
     * recipient under a different app, but must never be duplicated under the same one.
     *
     * The key spans the hash of the identity and not the value: the value is stored encrypted and the
     * cipher is not deterministic, so the same plain text encrypts differently on every write. See
     * Migration1792700020AddTransferRecipientIdentityHash.
     */
    public function testARecipientIdentityIsUniquePerApp(): void
    {
        $this->recreateTables();

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer_recipient',
            'uniq.payment_transfer_recipient.identity',
            ['payment_app_id', 'provider', 'identity_type', 'identity_hash']
        ));

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer_recipient',
            'idx.payment_transfer_recipient.identity_hash',
            ['identity_hash']
        ));

        // the foreign key to the app needs an index of its own - mysql refuses to drop the unique key
        // above while it is the only index the constraint can use (see Migration1792700020)
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer_recipient',
            'idx.payment_transfer_recipient.payment_app_id',
            ['payment_app_id']
        ));
    }

    public function testPaymentTransferTable(): void
    {
        $this->recreateTables();

        static::assertSame(
            [
                'id', 'payment_app_id', 'transfer_no', 'out_no', 'recipient_id', 'provider', 'channel',
                'amount', 'currency', 'channel_params', 'status_id', 'provider_ref', 'provider_stage_ref',
                'confirm_payload', 'failure_code', 'failure_reason', 'submit_attempts', 'expire_at',
                'submitted_at', 'settled_at', 'returned_at', 'canceled_at', 'metadata', 'created_at',
                'updated_at',
            ],
            $this->columnsOf('payment_transfer')
        );

        // the amount is always an integer in the smallest unit of the currency, never a decimal
        $amount = TableHelper::getColumnOfTable($this->connection, 'payment_transfer', 'amount');
        static::assertTrue($amount->isNotNull);
        static::assertSame('integer', $amount->type);

        $currency = TableHelper::getColumnOfTable($this->connection, 'payment_transfer', 'currency');
        static::assertTrue($currency->isNotNull);
        static::assertSame(3, $currency->length);

        // the submit attempts are counted by the platform and written by the creator, never defaulted
        $attempts = TableHelper::getColumnOfTable($this->connection, 'payment_transfer', 'submit_attempts');
        static::assertTrue($attempts->isNotNull);
        static::assertNull($attempts->defaultValue, 'submit_attempts is written explicitly by the creator');

        // everything the interface hands out later is nullable: the row exists before the request is sent
        foreach ([
            'provider_ref', 'provider_stage_ref', 'confirm_payload', 'failure_code', 'failure_reason',
            'expire_at', 'submitted_at', 'settled_at', 'returned_at', 'canceled_at', 'metadata',
        ] as $column) {
            static::assertFalse(
                TableHelper::getColumnOfTable($this->connection, 'payment_transfer', $column)->isNotNull,
                $column . ' must stay nullable'
            );
        }
    }

    /**
     * The business idempotency key is unique per app, the display number is unique on its own - a
     * retry reuses the out number, so a second number for the same payout must be impossible.
     */
    public function testTheTransferNumbersAreUnique(): void
    {
        $this->recreateTables();

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer',
            'uniq.payment_transfer.out_no',
            ['payment_app_id', 'out_no']
        ));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer',
            'uniq.payment_transfer.transfer_no',
            ['transfer_no']
        ));

        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer',
            'idx.payment_transfer.status_id',
            ['status_id']
        ));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'payment_transfer',
            'idx.payment_transfer.recipient_id',
            ['recipient_id']
        ));
    }

    /**
     * Every reference of a transfer is a real foreign key and restricted: a payout that lost its app,
     * its recipient or its state can not be reconciled anymore, and deleting the parent would silently
     * destroy the money trail.
     */
    public function testEveryReferenceOfATransferIsRestricted(): void
    {
        $this->recreateTables();

        $references = [
            ['payment_transfer', 'fk.payment_transfer.payment_app_id', 'payment_app'],
            ['payment_transfer', 'fk.payment_transfer.recipient_id', 'payment_transfer_recipient'],
            ['payment_transfer', 'fk.payment_transfer.status_id', 'state_machine_state'],
            ['payment_transfer_recipient', 'fk.payment_transfer_recipient.payment_app_id', 'payment_app'],
        ];

        foreach ($references as [$table, $foreignKey, $referencedTable]) {
            static::assertTrue(TableHelper::foreignKeyExists($this->connection, $table, $foreignKey), $foreignKey . ' was not created');
            static::assertSame($referencedTable, $this->referencedTableOf($table, $foreignKey));
            static::assertSame('RESTRICT', $this->deleteRuleOf($table, $foreignKey));
        }
    }

    public function testMigrationImportsTheTransferStateMachine(): void
    {
        $this->recreateTables();

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine` WHERE `technical_name` = :name',
            ['name' => PaymentTransferStates::STATE_MACHINE]
        ));

        $states = $this->connection->fetchFirstColumn(
            'SELECT `technical_name` FROM `state_machine_state` WHERE `state_machine_id` = (
                SELECT `id` FROM `state_machine` WHERE `technical_name` = :name
            )',
            ['name' => PaymentTransferStates::STATE_MACHINE]
        );

        static::assertCount(8, $states);
        static::assertEqualsCanonicalizing([
            PaymentTransferStates::STATE_CREATED,
            PaymentTransferStates::STATE_SUBMITTED,
            PaymentTransferStates::STATE_AWAITING_USER_CONFIRM,
            PaymentTransferStates::STATE_PROCESSING,
            PaymentTransferStates::STATE_SUCCEEDED,
            PaymentTransferStates::STATE_FAILED,
            PaymentTransferStates::STATE_CANCELED,
            PaymentTransferStates::STATE_RETURNED,
        ], $states);

        static::assertSame(15, $this->countTransitions());
    }

    /**
     * The edges that look counterintuitive are the ones that keep the money trail correct: stripe
     * reports `paid` and turns it into `failed` within five business days, alipay refunds a transfer
     * to a card the bank refused, paypal returns a payout nobody claimed within 30 days. Wechat needs
     * the confirmation flow, and cancels a running transfer only through `CANCELING`.
     */
    public function testTheCounterintuitiveEdgesExist(): void
    {
        $this->recreateTables();

        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_FAILED));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_MARK_RETURNED, PaymentTransferStates::STATE_SUCCEEDED, PaymentTransferStates::STATE_RETURNED));

        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_AWAIT_CONFIRM, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_PROCESS, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, PaymentTransferStates::STATE_PROCESSING));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_CANCEL, PaymentTransferStates::STATE_PROCESSING, PaymentTransferStates::STATE_CANCELED));

        // a synchronous success and a synchronous failure both come straight from `submitted`
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_SUCCEED, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_SUCCEEDED));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_FAIL, PaymentTransferStates::STATE_SUBMITTED, PaymentTransferStates::STATE_FAILED));
        static::assertTrue($this->transitionExists(PaymentTransferStates::ACTION_SUCCEED, PaymentTransferStates::STATE_AWAITING_USER_CONFIRM, PaymentTransferStates::STATE_SUCCEEDED));
    }

    /**
     * The initial state is the only one a transfer is created in, and nothing leads back into it.
     */
    public function testTheInitialStateHasNoIncomingTransition(): void
    {
        $this->recreateTables();

        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `to_state`.`technical_name` = :state',
            ['machine' => PaymentTransferStates::STATE_MACHINE, 'state' => PaymentTransferStates::STATE_CREATED]
        ));
    }

    /**
     * An action must resolve to exactly one destination state per origin: the registry looks the
     * destination up by (machine, action, from state) and deprecates - from 6.8 throws on - an action
     * that resolves to several. The subscription machine once pointed `fail` at both failure states,
     * which made one of them unreachable while every unit test stayed green.
     */
    public function testAnActionNeverHasTwoDestinationsFromOneState(): void
    {
        $this->recreateTables();

        $ambiguous = $this->connection->fetchAllAssociative(
            'SELECT `machine`.`technical_name` AS machine,
                    `transition`.`action_name` AS action,
                    `transition`.`from_state_id` AS from_state,
                    COUNT(DISTINCT `transition`.`to_state_id`) AS destinations
                FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                GROUP BY `machine`.`technical_name`, `transition`.`action_name`, `transition`.`from_state_id`
                HAVING destinations > 1',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );

        static::assertSame([], $ambiguous, 'a state machine action must resolve to exactly one destination state');
    }

    /**
     * The transfer references the recipient, which references the state machine and the app - the
     * tables only drop in the reverse order they were built in.
     *
     * The identity hash and the channel parameters are added by the migrations that follow this one,
     * and this test asserts the shape the chain produces - the table of a fresh installation, not the
     * intermediate state of an upgrade. Every later migration of this table has to be appended here:
     * this helper is what rebuilds the table of the test database, so a migration left out of it means
     * every later test reads a column that the table does not have.
     */
    private function recreateTables(): void
    {
        // the notification table points at the transfer table, and MySQL does not drop a table an
        // existing constraint still names: the constraint goes first and the migration that adds it
        // is replayed at the end of the chain below
        if (TableHelper::foreignKeyExists($this->connection, 'payment_notification', 'fk.payment_notification.payment_transfer_id')) {
            $this->connection->executeStatement('ALTER TABLE `payment_notification` DROP FOREIGN KEY `fk.payment_notification.payment_transfer_id`');
        }

        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_transfer`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_transfer_recipient`');

        // This test asserts what this migration itself produces, but the kernel bootstrap runs the
        // whole migration chain against the test database first, so transitions of later migrations
        // would otherwise be counted into this one's numbers. Only the transitions are reset - the
        // state definitions are add-only and idempotent, re-importing them inserts nothing - and
        // only for this machine, the other machines in this database (order, document, ...) must
        // stay untouched.
        $this->connection->executeStatement(
            'DELETE FROM `state_machine_transition` WHERE `state_machine_id` = (
                SELECT `id` FROM `state_machine` WHERE `technical_name` = :machine
            )',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );

        (new Migration1792700010CreatePaymentTransferTables())->update($this->connection);
        (new Migration1792700020AddTransferRecipientIdentityHash())->update($this->connection);
        (new Migration1792700040AddTransferChannelParams())->update($this->connection);
        (new Migration1792700050AddTransferToPaymentNotification())->update($this->connection);
    }

    /**
     * @param non-empty-string $table
     *
     * @return list<string>
     */
    private function columnsOf(string $table): array
    {
        return array_column(TableHelper::getTable($this->connection, $table)->columns, 'name');
    }

    private function transitionExists(string $action, string $from, string $to): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine_state` `from_state` ON `from_state`.`id` = `transition`.`from_state_id`
                INNER JOIN `state_machine_state` `to_state` ON `to_state`.`id` = `transition`.`to_state_id`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine
                    AND `transition`.`action_name` = :action
                    AND `from_state`.`technical_name` = :from
                    AND `to_state`.`technical_name` = :to',
            ['machine' => PaymentTransferStates::STATE_MACHINE, 'action' => $action, 'from' => $from, 'to' => $to]
        );

        return (int) $count > 0;
    }

    private function countTransitions(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `state_machine_transition` `transition`
                INNER JOIN `state_machine` `machine` ON `machine`.`id` = `transition`.`state_machine_id`
                WHERE `machine`.`technical_name` = :machine',
            ['machine' => PaymentTransferStates::STATE_MACHINE]
        );
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

    private function referencedTableOf(string $table, string $foreignKey): string
    {
        return (string) $this->connection->fetchOne(
            'SELECT `REFERENCED_TABLE_NAME` FROM `information_schema`.`KEY_COLUMN_USAGE`
                WHERE `TABLE_SCHEMA` = DATABASE()
                    AND `TABLE_NAME` = :table
                    AND `CONSTRAINT_NAME` = :foreignKey
                LIMIT 1',
            ['table' => $table, 'foreignKey' => $foreignKey]
        );
    }
}
