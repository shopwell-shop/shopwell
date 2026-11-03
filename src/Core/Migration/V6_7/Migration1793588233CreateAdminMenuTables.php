<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1793588233CreateAdminMenuTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793588233;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_menu_item` (
                `id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `after_menu_item_id` BINARY(16) NULL,
                `internal_key` VARCHAR(255) NULL,
                `group_name` VARCHAR(50) NULL,
                `icon` VARCHAR(255) NULL,
                `color` VARCHAR(20) NULL,
                `type` VARCHAR(20) NOT NULL,
                `link_target` VARCHAR(255) NULL,
                `privilege` VARCHAR(255) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `sys_locked` TINYINT(1) NOT NULL DEFAULT 0,
                `plugin_id` BINARY(16) NULL,
                `default_meta` JSON NULL,
                `config_overridden` TINYINT(1) NOT NULL DEFAULT 0,
                `archived` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.admin_menu_item.parent_id` (`parent_id`),
                KEY `idx.admin_menu_item.internal_key` (`internal_key`),
                KEY `idx.admin_menu_item.plugin_id` (`plugin_id`),
                UNIQUE KEY `uniq.admin_menu_item.plugin_id__internal_key` (`plugin_id`, `internal_key`),
                CONSTRAINT `fk.admin_menu_item.parent_id` FOREIGN KEY (`parent_id`) REFERENCES `admin_menu_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.admin_menu_item.after_menu_item_id` FOREIGN KEY (`after_menu_item_id`) REFERENCES `admin_menu_item` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.admin_menu_item.plugin_id` FOREIGN KEY (`plugin_id`) REFERENCES `plugin` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_menu_item_translation` (
                `admin_menu_item_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NULL,
                `group_title` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`admin_menu_item_id`, `language_id`),
                KEY `idx.admin_menu_item_translation.language_id` (`language_id`),
                CONSTRAINT `fk.admin_menu_item_translation.admin_menu_item_id` FOREIGN KEY (`admin_menu_item_id`) REFERENCES `admin_menu_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.admin_menu_item_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `admin_menu_item_role` (
                `admin_menu_item_id` BINARY(16) NOT NULL,
                `acl_role_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`admin_menu_item_id`, `acl_role_id`),
                KEY `idx.admin_menu_item_role.acl_role_id` (`acl_role_id`),
                CONSTRAINT `fk.admin_menu_item_role.admin_menu_item_id` FOREIGN KEY (`admin_menu_item_id`) REFERENCES `admin_menu_item` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.admin_menu_item_role.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
