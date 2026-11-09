<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1638993987AddAppFlowActionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1638993987;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `app_flow_action` (
                `id` BINARY(16) NOT NULL,
                `app_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `badge` VARCHAR(255) NULL,
                `url` VARCHAR(500) NOT NULL,
                `delayable` TINYINT(1) NOT NULL DEFAULT '0',
                `parameters` JSON NULL,
                `config` JSON NULL,
                `headers` JSON NULL,
                `requirements` JSON NULL,
                `icon` MEDIUMBLOB NULL,
                `sw_icon` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.app_flow_action.name` (`name`),
                KEY `fk.app_flow_action.app_id` (`app_id`),
                CONSTRAINT `fk.app_flow_action.app_id` FOREIGN KEY (`app_id`) REFERENCES `app` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.app_flow_action.config` CHECK (JSON_VALID(`config`)),
                CONSTRAINT `json.app_flow_action.headers` CHECK (JSON_VALID(`headers`)),
                CONSTRAINT `json.app_flow_action.parameters` CHECK (JSON_VALID(`parameters`)),
                CONSTRAINT `json.app_flow_action.requirements` CHECK (JSON_VALID(`requirements`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `app_flow_action_translation` (
                `app_flow_action_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `description` LONGTEXT COLLATE UTF8MB4_UNICODE_CI,
                `headline` VARCHAR(255) COLLATE UTF8MB4_UNICODE_CI DEFAULT NULL,
                `custom_fields` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`app_flow_action_id`,`language_id`),
                KEY `fk.app_flow_action_translation.language_id` (`language_id`),
                CONSTRAINT `fk.app_flow_action_translation.app_flow_action_id` FOREIGN KEY (`app_flow_action_id`) REFERENCES `app_flow_action` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.app_flow_action_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `json.app_flow_action_translation.custom_fields` CHECK (JSON_VALID(`custom_fields`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
