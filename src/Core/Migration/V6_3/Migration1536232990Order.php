<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232990Order extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232990;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `order` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `auto_increment` BIGINT unsigned NOT NULL AUTO_INCREMENT,
                `order_number` VARCHAR(64) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `currency_id` BINARY(16) NOT NULL,
                `currency_factor` DOUBLE NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `billing_address_id` BINARY(16) NOT NULL,
                `billing_address_version_id` BINARY(16) NOT NULL,
                `price` JSON NOT NULL,
                `order_date_time` DATETIME(3) NOT NULL,
                `order_date` DATE GENERATED ALWAYS AS (CAST(`order_date_time` AS DATE)) STORED,
                `amount_total` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.totalPrice"))) VIRTUAL,
                `amount_net` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.netPrice"))) VIRTUAL,
                `position_price` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, "$.positionPrice"))) VIRTUAL,
                `tax_status` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`,'$.taxStatus'))) VIRTUAL,
                `shipping_costs` JSON NOT NULL,
                `shipping_total` DOUBLE GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`shipping_costs`, "$.totalPrice"))) VIRTUAL,
                `deep_link_code` VARCHAR(32) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `rule_ids` JSON DEFAULT NULL,
                `created_by_id` BINARY(16) DEFAULT NULL,
                `updated_by_id` BINARY(16) DEFAULT NULL,
                `source` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `internal_comment` LONGTEXT COLLATE UTF8MB4_UNICODE_CI,
                `tax_calculation_type` VARCHAR(50) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                PRIMARY KEY (`id`, `version_id`),
                UNIQUE `uniq.auto_increment` (`auto_increment`),
                UNIQUE `uniq.deep_link_code` (`deep_link_code`, `version_id`),
                INDEX `idx.state_index` (`state_id`),
                KEY `fk.order.currency_id` (`currency_id`),
                KEY `fk.order.sales_channel_id` (`sales_channel_id`),
                KEY `idx.order_date_currency_id` (`order_date`,`currency_id`),
                KEY `idx.order_number` (`order_number`),
                KEY `fk.order.created_by_id` (`created_by_id`),
                KEY `fk.order.updated_by_id` (`updated_by_id`),
                CONSTRAINT `fk.order.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.order.currency_id` FOREIGN KEY (`currency_id`) REFERENCES `currency` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.order.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.order.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `char_length.order.deep_link_code` CHECK (CHAR_LENGTH(`deep_link_code`) = 32),
                CONSTRAINT `json.order.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `json.order.price` CHECK (JSON_VALID(`price`)),
                CONSTRAINT `json.order.shipping_costs` CHECK (JSON_VALID(`shipping_costs`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
