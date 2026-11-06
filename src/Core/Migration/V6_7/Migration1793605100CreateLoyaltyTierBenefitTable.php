<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the benefits a tier grants, one row per benefit type.
 *
 * A tier used to carry three fixed columns (`points_multiplier`, `discount_percent`, `free_shipping`).
 * Every new idea - a birthday gift, a different point lifetime, early access to a sale - needed another
 * column, another migration and another form field, while the three existing ones were only ever read
 * in two places. The table turns the closed set into an open one: the benefit type names the rule, the
 * value carries its single parameter, and a tier simply has the rows it grants.
 *
 * `uniq.loyalty_tier_benefit.type` makes "one tier, one row per type" a fact of the schema instead of a
 * convention. Two `earn_multiplier` rows would raise the question which of them wins, and no answer to
 * that question is better than not having to ask it.
 *
 * There are no rows to copy: the three columns never reached a released installation, so the create
 * table statement of `loyalty_tier` simply does not declare them anymore. A backfill would copy data
 * that cannot exist, and keeping it would force the columns to stay.
 *
 * @internal
 */
#[Package('framework')]
class Migration1793605100CreateLoyaltyTierBenefitTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793605100;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `loyalty_tier_benefit` (
                `id` BINARY(16) NOT NULL,
                `loyalty_tier_id` BINARY(16) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `value` DOUBLE NOT NULL DEFAULT 0,
                `position` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.loyalty_tier_benefit.type` (`loyalty_tier_id`, `type`),
                CONSTRAINT `fk.loyalty_tier_benefit.loyalty_tier_id` FOREIGN KEY (`loyalty_tier_id`)
                    REFERENCES `loyalty_tier` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
