<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1597391970App extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1597391970;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `app` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI NOT NULL,
                `path` VARCHAR(4096) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `author` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `copyright` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `license` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `privacy` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `version` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI NOT NULL,
                `active` TINYINT(1) DEFAULT 0 NOT NULL,
                `allow_disable` TINYINT(1) NOT NULL DEFAULT '1',
                `configurable` TINYINT(1) NOT NULL DEFAULT '0',
                `icon` MEDIUMBLOB NULL,
                `app_secret` VARCHAR(255) CHARACTER SET UTF8MB4 COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `modules` JSON NULL,
                `main_module` JSON DEFAULT NULL,
                `integration_id` BINARY(16) NOT NULL,
                `acl_role_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                `template_load_priority` INT DEFAULT '0',
                `checkout_gateway_url` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `in_app_purchases_gateway_url` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `requested_privileges` JSON DEFAULT NULL,
                `context_gateway_url` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `unconfirmed_app_secrets` JSON DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.name` (`name`),
                KEY `fk.app.integration_id` (`integration_id`),
                KEY `fk.app.acl_role_id` (`acl_role_id`),
                CONSTRAINT `fk.app.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.app.integration_id` FOREIGN KEY (`integration_id`) REFERENCES `integration` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `json.app.main_module` CHECK (JSON_VALID(`main_module`)),
                CONSTRAINT `json.app.modules` CHECK (JSON_VALID(`modules`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `app_translation` (
                `app_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `description` LONGTEXT NULL,
                `privacy_policy_extensions` MEDIUMTEXT COLLATE UTF8MB4_UNICODE_CI,
                `custom_fields` JSON DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`app_id`,`language_id`),
                KEY `fk.app_translation.app_id` (`app_id`),
                KEY `fk.app_translation.language_id` (`language_id`),
                CONSTRAINT `fk.app_translation.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.app_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
