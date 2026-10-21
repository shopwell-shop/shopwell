<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * Creates the tables of the customer loyalty program (points, growth value and membership tiers)
 * and of the customer wallet (balances and ledger entries).
 *
 * @internal
 */
#[Package('framework')]
class Migration1792600000CreateLoyaltyAndWalletTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `loyalty_tier` (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) NOT NULL,
                `level` INT NOT NULL,
                `min_growth_value` INT NOT NULL DEFAULT 0,
                `points_multiplier` DOUBLE NOT NULL DEFAULT 1,
                `discount_percent` DOUBLE NOT NULL DEFAULT 0,
                `free_shipping` TINYINT(1) NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `customer_group_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.loyalty_tier.technical_name` (`technical_name`),
                KEY `idx.loyalty_tier.customer_group_id` (`customer_group_id`),
                CONSTRAINT `fk.loyalty_tier.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `loyalty_tier_translation` (
                `loyalty_tier_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `description` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`loyalty_tier_id`, `language_id`),
                KEY `idx.loyalty_tier_translation.language_id` (`language_id`),
                CONSTRAINT `fk.loyalty_tier_translation.loyalty_tier_id` FOREIGN KEY (`loyalty_tier_id`) REFERENCES `loyalty_tier` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.loyalty_tier_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `customer_loyalty` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `points_balance` INT NOT NULL DEFAULT 0,
                `growth_value` INT NOT NULL DEFAULT 0,
                `membership_tier_id` BINARY(16) NULL,
                `membership_valid_until` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.customer_loyalty.customer_id` (`customer_id`),
                KEY `idx.customer_loyalty.membership_tier_id` (`membership_tier_id`),
                CONSTRAINT `fk.customer_loyalty.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_loyalty.membership_tier_id` FOREIGN KEY (`membership_tier_id`) REFERENCES `loyalty_tier` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        // `order` is versionized - its primary key is (id, version_id) - so every foreign key into it
        // has to carry both columns, a plain `order_id` alone has no unique key to point at
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `customer_point_transaction` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `points` INT NOT NULL DEFAULT 0,
                `balance_after` INT NOT NULL DEFAULT 0,
                `idempotency_key` VARCHAR(255) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `sales_channel_id` BINARY(16) NULL,
                `expires_at` DATETIME(3) NULL,
                `expired_at` DATETIME(3) NULL,
                `comment` VARCHAR(255) NULL,
                `administrator_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.customer_point_transaction.idempotency_key` (`idempotency_key`),
                KEY `idx.customer_point_transaction.customer_id` (`customer_id`),
                KEY `idx.customer_point_transaction.order_id` (`order_id`, `order_version_id`),
                KEY `idx.customer_point_transaction.sales_channel_id` (`sales_channel_id`),
                KEY `idx.customer_point_transaction.expires_at` (`expires_at`),
                CONSTRAINT `fk.customer_point_transaction.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_point_transaction.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_point_transaction.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_point_transaction.administrator_id` FOREIGN KEY (`administrator_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `customer_wallet` (
                `id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `balance` DOUBLE NOT NULL DEFAULT 0,
                `locked_balance` DOUBLE NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.customer_wallet.customer_id_currency_id` (`customer_id`, `currency_id`),
                KEY `idx.customer_wallet.currency_id` (`currency_id`),
                CONSTRAINT `fk.customer_wallet.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_wallet.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS `customer_wallet_transaction` (
                `id` BINARY(16) NOT NULL,
                `customer_wallet_id` BINARY(16) NOT NULL,
                `type` VARCHAR(32) NOT NULL,
                `amount` DOUBLE NOT NULL DEFAULT 0,
                `balance_after` DOUBLE NOT NULL DEFAULT 0,
                `currency_id` BINARY(16) NULL,
                `order_id` BINARY(16) NULL,
                `order_version_id` BINARY(16) NULL,
                `external_reference` VARCHAR(255) NULL,
                `comment` VARCHAR(255) NULL,
                `administrator_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.customer_wallet_transaction.customer_wallet_id` (`customer_wallet_id`),
                KEY `idx.customer_wallet_transaction.order_id` (`order_id`, `order_version_id`),
                KEY `idx.customer_wallet_transaction.currency_id` (`currency_id`),
                CONSTRAINT `fk.customer_wallet_transaction.customer_wallet_id` FOREIGN KEY (`customer_wallet_id`) REFERENCES `customer_wallet` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_wallet_transaction.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_wallet_transaction.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.customer_wallet_transaction.administrator_id` FOREIGN KEY (`administrator_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // nothing to do here
    }
}
