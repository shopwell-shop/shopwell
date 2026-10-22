<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the tables of the memberships a customer can buy: the plans the shop offers and the orders
 * a customer placed.
 *
 * The order copies tier, duration and price of the plan, so a plan that is edited or removed later
 * cannot rewrite what was bought. The plan is therefore only the offer, the order is the purchase.
 *
 * @internal
 */
#[Package('framework')]
class Migration1792600011CreateMembershipTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792600011;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `membership_plan` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(64) NOT NULL,
                `loyalty_tier_id` BINARY(16) NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `price` DOUBLE NOT NULL DEFAULT 0,
                `duration_days` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.membership_plan.technical_name` (`technical_name`),
                KEY `idx.membership_plan.loyalty_tier_id` (`loyalty_tier_id`),
                KEY `idx.membership_plan.currency_id` (`currency_id`),
                CONSTRAINT `fk.membership_plan.loyalty_tier_id` FOREIGN KEY (`loyalty_tier_id`) REFERENCES `loyalty_tier` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_plan.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `membership_plan_translation` (
                `membership_plan_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `description` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`membership_plan_id`, `language_id`),
                KEY `idx.membership_plan_translation.language_id` (`language_id`),
                CONSTRAINT `fk.membership_plan_translation.membership_plan_id` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_plan_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `membership_order` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `membership_plan_id` BINARY(16) NULL,
                `loyalty_tier_id` BINARY(16) NULL,
                `plan_name` VARCHAR(255) NULL,
                `amount` DOUBLE NOT NULL DEFAULT 0,
                `currency_id` BINARY(16) NOT NULL,
                `currency` VARCHAR(3) NOT NULL,
                `duration_days` INT NOT NULL DEFAULT 0,
                `state` VARCHAR(32) NOT NULL,
                `payment_no` VARCHAR(64) NULL,
                `channel_technical_name` VARCHAR(64) NULL,
                `starts_at` DATETIME(3) NULL,
                `expires_at` DATETIME(3) NULL,
                `paid_at` DATETIME(3) NULL,
                `activated_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.membership_order.customer_id` (`customer_id`),
                KEY `idx.membership_order.membership_plan_id` (`membership_plan_id`),
                KEY `idx.membership_order.loyalty_tier_id` (`loyalty_tier_id`),
                KEY `idx.membership_order.currency_id` (`currency_id`),
                KEY `idx.membership_order.payment_no` (`payment_no`),
                KEY `idx.membership_order.state` (`state`),
                CONSTRAINT `fk.membership_order.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_order.membership_plan_id` FOREIGN KEY (`membership_plan_id`) REFERENCES `membership_plan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_order.loyalty_tier_id` FOREIGN KEY (`loyalty_tier_id`) REFERENCES `loyalty_tier` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.membership_order.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
