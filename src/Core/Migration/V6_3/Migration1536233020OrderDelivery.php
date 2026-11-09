<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536233020OrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536233020;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `order_delivery` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `state_id` BINARY(16) NOT NULL,
                `shipping_order_address_id` BINARY(16) NULL,
                `shipping_order_address_version_id` BINARY(16) NULL,
                `shipping_method_id` BINARY(16) NOT NULL,
                `tracking_code` VARCHAR(200) COLLATE utf8mb4_unicode_ci NULL,
                `shipping_date_earliest` DATE NOT NULL,
                `shipping_date_latest` DATE NOT NULL,
                `shipping_costs` JSON NOT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`, `version_id`),
                INDEX `idx.state_index` (`state_id`),
                KEY `fk.order_delivery.order_id` (`order_id`,`order_version_id`),
                KEY `fk.order_delivery.shipping_method_id` (`shipping_method_id`),
                KEY `fk.order_delivery.shipping_order_address_id` (`shipping_order_address_id`,`shipping_order_address_version_id`),
                CONSTRAINT `fk.order_delivery.order_id` FOREIGN KEY (`order_id`, `order_version_id`) REFERENCES `order` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.order_delivery.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `json.order_delivery.custom_fields` CHECK (JSON_VALID(`custom_fields`)),
                CONSTRAINT `json.order_delivery.shipping_costs` CHECK (JSON_VALID(`shipping_costs`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
