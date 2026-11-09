<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232980Cart extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232980;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `cart` (
                `token` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
                `cart` LONGTEXT NOT NULL,
                `price` FLOAT NOT NULL,
                `line_item_count` VARCHAR(42) COLLATE utf8mb4_unicode_ci NOT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `shipping_method_id` BINARY(16) NOT NULL,
                `payment_method_id` BINARY(16) NOT NULL,
                `country_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `rule_ids` JSON NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) DEFAULT NULL,
                `auto_increment` BIGINT NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`token`),
                UNIQUE KEY `auto_increment` (`auto_increment`),
                KEY `fk.cart.country_id` (`country_id`),
                KEY `fk.cart.currency_id` (`currency_id`),
                KEY `fk.cart.customer_id` (`customer_id`),
                KEY `fk.cart.payment_method_id` (`payment_method_id`),
                KEY `fk.cart.shipping_method_id` (`shipping_method_id`),
                KEY `fk.cart.sales_channel_id` (`sales_channel_id`),
                KEY `idx.cart.created_at` (`created_at`),
                CONSTRAINT `fk.cart.country_id` FOREIGN KEY (`country_id`) REFERENCES `country` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.cart.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.cart.customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.cart.payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.cart.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.cart.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
