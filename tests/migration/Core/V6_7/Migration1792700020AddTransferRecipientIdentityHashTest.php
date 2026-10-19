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
use Shopwell\Core\Migration\V6_7\Migration1792700050AddTransferToPaymentNotification;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792700020AddTransferRecipientIdentityHash::class)]
class Migration1792700020AddTransferRecipientIdentityHashTest extends TestCase
{
    private const TABLE = 'payment_transfer_recipient';

    private const UNIQUE_KEY = 'uniq.payment_transfer_recipient.identity';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        // the notification table points at the transfer table, and MySQL does not drop a table an
        // existing constraint still names: the constraint goes first and the migration that adds it
        // is replayed after the tables are back
        if (TableHelper::foreignKeyExists($this->connection, 'payment_notification', 'fk.payment_notification.payment_transfer_id')) {
            $this->connection->executeStatement('ALTER TABLE `payment_notification` DROP FOREIGN KEY `fk.payment_notification.payment_transfer_id`');
        }

        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_transfer`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `payment_transfer_recipient`');

        (new Migration1792700010CreatePaymentTransferTables())->update($this->connection);
        (new Migration1792700020AddTransferRecipientIdentityHash())->update($this->connection);
        (new Migration1792700050AddTransferToPaymentNotification())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792700020, (new Migration1792700020AddTransferRecipientIdentityHash())->getCreationTimestamp());
    }

    public function testTheMigrationIsIdempotent(): void
    {
        $migration = new Migration1792700020AddTransferRecipientIdentityHash();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, self::TABLE, 'identity_hash'));
        static::assertTrue(TableHelper::indexExists($this->connection, self::TABLE, self::UNIQUE_KEY));
        static::assertTrue(TableHelper::indexExists($this->connection, self::TABLE, 'idx.payment_transfer_recipient.identity_hash'));
    }

    /**
     * The hash is the deterministic half of the identity. It is not nullable and has no default - the
     * writer always knows the plain text it hashes, and a row without it could never be found again.
     */
    public function testTheIdentityHashColumn(): void
    {
        $column = TableHelper::getColumnOfTable($this->connection, self::TABLE, 'identity_hash');

        static::assertTrue($column->isNotNull);
        static::assertSame(64, $column->length);
        static::assertNull($column->defaultValue, 'the hash is always written by the creator of the recipient');
    }

    /**
     * The encrypted value can not be unique - the very same plain text encrypts differently on every
     * write - so the unique key moves to the hash. Its column order is what the service looks the
     * recipient up by.
     */
    public function testTheUniqueKeySpansTheHashInsteadOfTheValue(): void
    {
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            self::TABLE,
            self::UNIQUE_KEY,
            ['payment_app_id', 'provider', 'identity_type', 'identity_hash']
        ));

        static::assertFalse(
            TableHelper::indexSpansColumns(
                $this->connection,
                self::TABLE,
                self::UNIQUE_KEY,
                ['payment_app_id', 'provider', 'identity_type', 'identity_value']
            ),
            'the encrypted value must not be part of the unique key'
        );
    }

    /**
     * An encrypted column can not be searched, so the hash doubles as the index that answers "which
     * payouts went to this identity".
     */
    public function testTheHashIsIndexedForLookups(): void
    {
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            self::TABLE,
            'idx.payment_transfer_recipient.identity_hash',
            ['identity_hash']
        ));
    }

    /**
     * The foreign key to the app keeps an index of its own. Without it mysql refuses to rebuild the
     * unique key (error 1553), because that key is the only index the constraint can fall back on.
     */
    public function testTheAppKeepsAnIndexOfItsOwn(): void
    {
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            self::TABLE,
            'idx.payment_transfer_recipient.payment_app_id',
            ['payment_app_id']
        ));
    }
}
