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
            CREATE TABLE IF NOT EXISTS `customer` (
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
                `company` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `password` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NULL,
                `legacy_password` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `legacy_encoder` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `email` VARCHAR(254) COLLATE utf8mb4_unicode_ci NOT NULL,
                `phone_number` VARCHAR(40) COLLATE utf8mb4_unicode_ci NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `doubleOptInRegistration` TINYINT(1) NOT NULL DEFAULT '0',
                `doubleOptInEmailSentDate` DATETIME(3) DEFAULT NULL,
                `doubleOptInConfirmDate` DATETIME(3) DEFAULT NULL,
                `hash` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `guest` TINYINT(1) NOT NULL DEFAULT 0,
                `first_login` DATETIME(3) DEFAULT NULL,
                `last_login` DATETIME(3) NULL,
                `newsletter` TINYINT(1) NOT NULL DEFAULT '0',
                `birthday` DATE NULL,
                `last_order_date` DATETIME(3),
                `order_count` INT(5) NOT NULL DEFAULT 0,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `remote_address` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `tag_ids` JSON DEFAULT NULL,
                `bound_sales_channel_id` BINARY(16) DEFAULT NULL,
                `created_by_id` BINARY(16) DEFAULT NULL,
                `updated_by_id` BINARY(16) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE `uniq.auto_increment` (`auto_increment`),
                UNIQUE KEY `hash` (`hash`),
                KEY `idx.firstlogin` (`first_login`),
                KEY `idx.lastlogin` (`last_login`),
                KEY `idx.customer.default_billing_address_id` (`default_billing_address_id`),
                KEY `idx.customer.default_shipping_address_id` (`default_shipping_address_id`),
                KEY `fk.customer.customer_group_id` (`customer_group_id`),
                KEY `fk.customer.default_payment_method_id` (`default_payment_method_id`),
                KEY `fk.customer.last_payment_method_id` (`last_payment_method_id`),
                KEY `fk.customer.sales_channel_id` (`sales_channel_id`),
                KEY `fk.customer.bound_sales_channel_id` (`bound_sales_channel_id`),
                KEY `idx.email` (`email`),
                KEY `fk.customer.created_by_id` (`created_by_id`),
                KEY `fk.customer.updated_by_id` (`updated_by_id`),
                CONSTRAINT `fk.customer.bound_sales_channel_id` FOREIGN KEY (`bound_sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.customer.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.customer.customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.customer.last_payment_method_id` FOREIGN KEY (`last_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.customer.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.customer.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `json.customer.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `json.customer.tag_ids` CHECK (JSON_VALID(`tag_ids`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
