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
 * The three old columns are copied over once, so a shop that already configured a multiplier keeps it.
 * The copy is one statement per benefit type and not a loop over the tiers: a single statement either
 * writes all of its rows or none, so a failed copy cannot leave half a configuration behind and call
 * itself done. Re-running is safe because the join finds the rows that are already there.
 *
 * The old columns are not dropped. The administration form still binds them and the storefront template
 * still prints them; both move to this table in the UI step, and only then the columns go away. Until
 * that day {@see \Shopwell\Core\Checkout\Loyalty\Service\LoyaltyBenefitResolver} reads a benefit row
 * first and falls back to the column.
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

        $this->copyColumnBenefits($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }

    /**
     * Turns the three configured columns into rows of their natural type.
     *
     * A multiplier of exactly 1 and a discount of exactly 0 grant nothing, so they are not copied - the
     * absence of a row says "no such benefit" and keeps the table readable. Free shipping is stored as
     * 1 because a benefit value is a number; `0` is skipped for the same reason.
     *
     * The type and the column names are literals from this file, never input, which is why they are
     * inlined while the values stay parameters.
     */
    private function copyColumnBenefits(Connection $connection): void
    {
        $this->copyBenefit($connection, 'earn_multiplier', 'points_multiplier', '1.0', 10);
        $this->copyBenefit($connection, 'cart_discount', 'discount_percent', '0.0', 20);

        $connection->executeStatement(
            'INSERT INTO `loyalty_tier_benefit` (`id`, `loyalty_tier_id`, `type`, `value`, `position`, `created_at`)
             SELECT UNHEX(REPLACE(UUID(), \'-\', \'\')), t.`id`, \'free_shipping\', 1, 30, NOW(3)
             FROM `loyalty_tier` t
             LEFT JOIN `loyalty_tier_benefit` b
                 ON b.`loyalty_tier_id` = t.`id` AND b.`type` = \'free_shipping\'
             WHERE b.`id` IS NULL AND t.`free_shipping` = 1'
        );
    }

    /**
     * @param string $neutral the value that means "this tier grants nothing", not copied
     */
    private function copyBenefit(Connection $connection, string $type, string $column, string $neutral, int $position): void
    {
        $connection->executeStatement(
            \sprintf(
                'INSERT INTO `loyalty_tier_benefit` (`id`, `loyalty_tier_id`, `type`, `value`, `position`, `created_at`)
                 SELECT UNHEX(REPLACE(UUID(), \'-\', \'\')), t.`id`, \'%1$s\', t.`%2$s`, %3$d, NOW(3)
                 FROM `loyalty_tier` t
                 LEFT JOIN `loyalty_tier_benefit` b
                     ON b.`loyalty_tier_id` = t.`id` AND b.`type` = \'%1$s\'
                 WHERE b.`id` IS NULL AND t.`%2$s` IS NOT NULL AND t.`%2$s` <> %4$s',
                $type,
                $column,
                $position,
                $neutral
            )
        );
    }
}
