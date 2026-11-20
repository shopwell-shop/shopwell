<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795084152CreateApprovalFlowInstanceTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795084152;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_instance` (
                `id` BINARY(16) NOT NULL,
                `release_id` BINARY(16) NOT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `outcome` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `aggregate_version` INT UNSIGNED NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.approval_flow_instance.status` (`status`, `created_at`, `id`),
                CONSTRAINT `fk.approval_flow_instance.release` FOREIGN KEY (`release_id`) REFERENCES `approval_flow_release` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_instance.status` CHECK (`status` IN ('draft', 'running', 'suspended', 'completed', 'terminated', 'withdrawn')),
                CONSTRAINT `chk.approval_flow_instance.outcome` CHECK (`outcome` IN ('approved', 'rejected')),
                CONSTRAINT `chk.approval_flow_instance.version` CHECK (`aggregate_version` > 0)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_operation` (
                `instance_id` BINARY(16) NOT NULL,
                `command_id` BINARY(16) NOT NULL,
                `operation` VARCHAR(64) NOT NULL,
                `request_hash` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `actor_json` LONGTEXT NOT NULL,
                `reason` LONGTEXT NOT NULL,
                `previous_status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `outcome` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `previous_version` INT UNSIGNED NOT NULL,
                `aggregate_version` INT UNSIGNED NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`instance_id`, `command_id`),
                UNIQUE KEY `uniq.approval_flow_operation.version` (`instance_id`, `aggregate_version`),
                CONSTRAINT `fk.approval_flow_operation.instance` FOREIGN KEY (`instance_id`) REFERENCES `approval_flow_instance` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_operation.previous_status` CHECK (`previous_status` IN ('draft', 'running', 'suspended', 'completed', 'terminated', 'withdrawn')),
                CONSTRAINT `chk.approval_flow_operation.status` CHECK (`status` IN ('draft', 'running', 'suspended', 'completed', 'terminated', 'withdrawn')),
                CONSTRAINT `chk.approval_flow_operation.outcome` CHECK (`outcome` IN ('approved', 'rejected')),
                CONSTRAINT `chk.approval_flow_operation.version` CHECK (`previous_version` > 0 AND `aggregate_version` = `previous_version` + 1),
                CONSTRAINT `json.approval_flow_operation.actor` CHECK (JSON_VALID(`actor_json`))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
