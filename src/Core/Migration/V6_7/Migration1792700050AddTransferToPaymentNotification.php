<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * A payout notification is recorded in the same table a payment notification is, but it belongs to a
 * transfer and not to an order: the two are different domains with different state machines, and the
 * column that names the row has to say which one it is.
 *
 * Nullable on purpose. Every existing row was written before a transfer could notify the platform at
 * all, and a notification is stored before it is even looked at - the row therefore cannot wait for
 * the transfer to be known. The reference is filled in once the signed body named the payout.
 *
 * The foreign key is added apart from the column and guarded on its own: the migration tests of the
 * transfer tables rebuild `payment_transfer`, and MySQL refuses to drop a table another table points
 * at. Those tests drop this constraint first and replay this migration afterwards, so a guard that
 * only looked at the column would leave them with a column and no constraint.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792700050AddTransferToPaymentNotification extends MigrationStep
{
    private const TABLE = 'payment_notification';

    private const COLUMN = 'payment_transfer_id';

    private const INDEX = 'idx.payment_notification.payment_transfer_id';

    private const FOREIGN_KEY = 'fk.payment_notification.payment_transfer_id';

    public function getCreationTimestamp(): int
    {
        return 1792700050;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, self::TABLE, self::COLUMN)) {
            $connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '`
                    ADD COLUMN `' . self::COLUMN . '` BINARY(16) NULL AFTER `payment_order_id`'
            );
        }

        if (TableHelper::foreignKeyExists($connection, self::TABLE, self::FOREIGN_KEY)) {
            return;
        }

        if (!TableHelper::indexExists($connection, self::TABLE, self::INDEX)) {
            $connection->executeStatement(
                'ALTER TABLE `' . self::TABLE . '`
                    ADD INDEX `' . self::INDEX . '` (`' . self::COLUMN . '`)'
            );
        }

        // the same cascade the payment order reference has: a notification is evidence of a row that
        // no longer exists for nobody, so it goes with the payout it belongs to
        $connection->executeStatement(
            'ALTER TABLE `' . self::TABLE . '`
                ADD CONSTRAINT `' . self::FOREIGN_KEY . '`
                FOREIGN KEY (`' . self::COLUMN . '`) REFERENCES `payment_transfer` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
