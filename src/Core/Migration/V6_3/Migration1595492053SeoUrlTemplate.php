<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1595492053SeoUrlTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595492053;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `seo_url_template` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `route_name` VARCHAR(255) NOT NULL,
                `entity_name` VARCHAR(64) NOT NULL,
                `template` VARCHAR(750) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `is_valid` TINYINT(1) NOT NULL DEFAULT 1,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.seo_url_template.route_name` (`sales_channel_id`,`route_name`),
                CONSTRAINT `fk.seo_url_template.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `json.seo_url_template.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
