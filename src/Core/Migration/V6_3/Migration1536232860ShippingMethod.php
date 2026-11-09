<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232860ShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232860;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `shipping_method` (
                `id` BINARY(16) NOT NULL,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
                `position` INT NOT NULL DEFAULT '1',
                `availability_rule_id` BINARY(16) DEFAULT NULL,
                `media_id` BINARY(16) NULL,
                `delivery_time_id` BINARY(16) NOT NULL,
                `tax_type` VARCHAR(50) COLLATE UTF8MB4_UNICODE_CI DEFAULT 'auto',
                `tax_id` BINARY(16) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `fk.shipping_method.media_id` (`media_id`),
                KEY `fk.shipping_method.availability_rule_id` (`availability_rule_id`),
                KEY `fk.shipping_method.delivery_time_id` (`delivery_time_id`),
                KEY `fk.shipping_method.tax_id` (`tax_id`),
                CONSTRAINT `fk.shipping_method.availability_rule_id` FOREIGN KEY (`availability_rule_id`) REFERENCES `rule` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.shipping_method.delivery_time_id` FOREIGN KEY (`delivery_time_id`) REFERENCES `delivery_time` (`id`),
                CONSTRAINT `fk.shipping_method.media_id` FOREIGN KEY (media_id) REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.shipping_method.tax_id` FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `shipping_method_translation` (
                `shipping_method_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `description` MEDIUMTEXT COLLATE utf8mb4_unicode_ci NULL,
                `tracking_url` MEDIUMTEXT COLLATE UTF8MB4_UNICODE_CI,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`shipping_method_id`, `language_id`),
                KEY `fk.shipping_method_translation.language_id` (`language_id`),
                CONSTRAINT `fk.shipping_method_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.shipping_method_translation.shipping_method_id` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_method` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.shipping_method_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
