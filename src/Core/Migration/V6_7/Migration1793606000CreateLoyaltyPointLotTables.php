<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Splits the point balance into the batches it was earned in.
 *
 * A balance alone cannot answer the two questions a loyalty program is asked every day: which points
 * expire next, and which points the customer just spent. The old expiration task could only compare
 * "everything that expires today" against "the balance", so consuming 200 of 300 points left 100 points
 * with no history - the expired amount was a guess and no receipt could explain it.
 *
 * A lot is one delivery of points: it keeps how many arrived (`points`, immutable), how many are left
 * (`remaining_points`), and when it dies (`expires_at`). Spending takes from the lot that expires first,
 * which is the rule customers expect and the only one that survives an audit.
 *
 * `loyalty_point_lot_use` records which buckets one booking emptied. Without it a refund could only
 * hand points back with a fresh expiry date, which silently extends what the customer bought.
 * `returned_points` on that row is how much of the withdrawal a return already gave back: a return can
 * come in parts, and the row is what keeps the second one from giving the same points twice.
 *
 * The backfill is not a nicety: once spending walks the lots, a balance without lots is unspendable.
 * It therefore derives the consumed part of every customer from `award_total - balance` and spreads it
 * over the lots in expiry order - the same FIFO the runtime uses. It runs once, guarded by the table
 * being empty, because the statement would otherwise duplicate the whole history on a re-run.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793606000CreateLoyaltyPointLotTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793606000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `loyalty_point_lot` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `points` INT NOT NULL,
                `remaining_points` INT NOT NULL DEFAULT 0,
                `expires_at` DATETIME(3) NULL,
                `expired_at` DATETIME(3) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.loyalty_point_lot.customer_expires` (`customer_id`, `expires_at`, `created_at`),
                KEY `idx.loyalty_point_lot.expires_remaining` (`expires_at`, `remaining_points`),
                KEY `idx.loyalty_point_lot.order_id` (`order_id`, `order_version_id`),
                CONSTRAINT `fk.loyalty_point_lot.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.loyalty_point_lot.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `loyalty_point_lot_use` (
                `id` BINARY(16) NOT NULL,
                `lot_id` BINARY(16) NOT NULL,
                `transaction_id` BINARY(16) NOT NULL,
                `points` INT NOT NULL,
                `returned_points` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.loyalty_point_lot_use.lot_id` (`lot_id`),
                KEY `idx.loyalty_point_lot_use.transaction_id` (`transaction_id`),
                CONSTRAINT `fk.loyalty_point_lot_use.lot_id` FOREIGN KEY (`lot_id`) REFERENCES `loyalty_point_lot` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.loyalty_point_lot_use.transaction_id` FOREIGN KEY (`transaction_id`) REFERENCES `customer_point_transaction` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        if ((int) $connection->fetchOne('SELECT COUNT(*) FROM `loyalty_point_lot`') > 0) {
            return;
        }

        $this->backfillLots($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    /**
     * Turns every booking that ever added points into a lot, then takes the already spent part off the
     * lots in the order they expire.
     *
     * `award_total - balance` is the amount the customer consumed. It also catches negative manual
     * adjustments, which is why they count as consumption rather than as a gap in the history.
     */
    private function backfillLots(Connection $connection): void
    {
        $connection->executeStatement(
            'INSERT INTO `loyalty_point_lot`
                (`id`, `customer_id`, `points`, `remaining_points`, `expires_at`, `order_id`, `order_version_id`, `created_at`)
            SELECT
                UNHEX(REPLACE(UUID(), \'-\', \'\')),
                earned.`customer_id`,
                earned.`points`,
                GREATEST(0, LEAST(earned.`points`, earned.`cumulative` - COALESCE(totals.`award_total` - loyalty.`points_balance`, 0))),
                earned.`expires_at`,
                earned.`order_id`,
                earned.`order_version_id`,
                earned.`created_at`
            FROM (
                SELECT
                    booking.`customer_id`,
                    booking.`points`,
                    booking.`expires_at`,
                    booking.`order_id`,
                    booking.`order_version_id`,
                    booking.`created_at`,
                    SUM(booking.`points`) OVER (
                        PARTITION BY booking.`customer_id`
                        ORDER BY (booking.`expires_at` IS NULL), booking.`expires_at`, booking.`created_at`, booking.`id`
                        ROWS UNBOUNDED PRECEDING
                    ) AS `cumulative`
                FROM `customer_point_transaction` booking
                WHERE booking.`points` > 0 AND booking.`type` IN (\'award\', \'adjust\')
            ) earned
            INNER JOIN (
                SELECT `customer_id`, SUM(`points`) AS `award_total`
                FROM `customer_point_transaction`
                WHERE `points` > 0 AND `type` IN (\'award\', \'adjust\')
                GROUP BY `customer_id`
            ) totals ON totals.`customer_id` = earned.`customer_id`
            LEFT JOIN `customer_loyalty` loyalty ON loyalty.`customer_id` = earned.`customer_id`
            WHERE GREATEST(0, LEAST(earned.`points`, earned.`cumulative` - COALESCE(totals.`award_total` - loyalty.`points_balance`, 0))) > 0;'
        );
    }
}
