<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * The columns `default_billing_address_id` and `default_shipping_address_id` are nullable, because a
 * customer may register with email and password only and provide the addresses later during checkout.
 * In this development baseline the schema is recreated from the migrations
 * (`system:install --drop-database`), so the nullability is defined here instead of in a separate migration.
 */
#[Package('framework')]
class Migration1536232960Customer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232960;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<'SQL'
        CREATE TABLE `customer` (
              `id` BINARY(16) NOT NULL,
              `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
              `customer_group_id` BINARY(16) NOT NULL,
              `default_payment_method_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `last_payment_method_id` BINARY(16) NULL,
              `default_billing_address_id` BINARY(16) NULL,
              `default_shipping_address_id` BINARY(16) NULL,
              `customer_number` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `gender` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `company`     VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `password` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NULL,
              `legacy_password` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `legacy_encoder` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
              `email` VARCHAR(254) COLLATE utf8mb4_unicode_ci NOT NULL,
              `title` VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL,
              `phone_number` VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL,
              `active` TINYINT(1) NOT NULL DEFAULT 1,
              `guest` TINYINT(1) NOT NULL DEFAULT 0,
              `first_login` DATE NULL,
              `last_login` DATETIME(3) NULL,
              `newsletter` TINYINT(1) NOT NULL DEFAULT '0',
              `birthday` DATE NULL,
              `last_order_date` DATETIME(3),
              `order_count` INT(5) NOT NULL DEFAULT 0,
              `custom_fields` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.auto_increment` (`auto_increment`),
              KEY `idx.firstlogin` (`first_login`),
              KEY `idx.lastlogin` (`last_login`),
              KEY `idx.customer.default_billing_address_id` (`default_billing_address_id`),
              KEY `idx.customer.default_shipping_address_id` (`default_shipping_address_id`),
              CONSTRAINT `json.customer.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
              CONSTRAINT `fk.customer.customer_group_id` FOREIGN KEY (`customer_group_id`)
                REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`)
                REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
              CONSTRAINT `fk.customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
