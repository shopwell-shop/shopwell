<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the ladder that decides what a point is worth when it is spent.
 *
 * Until now one setting answered that for every redemption: the hundredth point was worth as much as
 * the ten-thousandth, so a customer who saved for half a year was rewarded exactly like one who spent
 * immediately. A ladder fixes that by making the value of a point grow with the number of points that
 * are redeemed in one order - the same idea as a progressive tariff.
 *
 * Each row marks the point count from which a better rate applies. The rows are read as bands: up to
 * the first threshold the configured `core.loyalty.pointValueInCurrency` applies, from the first
 * threshold to the second that row's rate, and so on. Nothing is seeded on purpose: an empty table
 * means "one rate for everything", which is exactly what the shop did before this table existed.
 *
 * No sales channel column: a shop runs one loyalty programme. The fallback rate keeps its per channel
 * override because it is a setting, not a rule of the programme.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793604700CreateLoyaltyRedemptionLevelTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793604700;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `loyalty_redemption_level` (
                `id` BINARY(16) NOT NULL,
                `points` INT NOT NULL,
                `value_per_point` DOUBLE NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.loyalty_redemption_level.points` (`points`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
