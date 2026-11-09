<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1536232630PropertyGroup extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232630;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `property_group` (
                `id` BINARY(16) NOT NULL,
                `sorting_type` VARCHAR(50) NOT NULL DEFAULT 'alphanumeric',
                `display_type` VARCHAR(50) NOT NULL DEFAULT 'text',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `filterable` TINYINT(1) NOT NULL DEFAULT '1',
                `visible_on_product_detail_page` TINYINT(1) DEFAULT '1',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `property_group_translation` (
                `property_group_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
                `description` LONGTEXT NULL,
                `position` INT DEFAULT '1',
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`property_group_id`, `language_id`),
                KEY `fk.property_group_translation.language_id` (`language_id`),
                CONSTRAINT `fk.property_group_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.property_group_translation.property_group_id` FOREIGN KEY (`property_group_id`) REFERENCES `property_group` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.property_group_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
