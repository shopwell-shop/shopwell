<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795141719CreateApprovalFlowBindingTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795141719;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_binding` (
                `id` BINARY(16) NOT NULL,
                `business_type` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `business_version` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `revision` INT UNSIGNED NOT NULL,
                `release_id` BINARY(16) NOT NULL,
                `form_schema_id` LONGTEXT NOT NULL,
                `form_schema_hash` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `form_schema` LONGTEXT NOT NULL,
                `created_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_binding.revision` (`business_type`, `revision`),
                CONSTRAINT `fk.approval_flow_binding.release` FOREIGN KEY (`release_id`) REFERENCES `approval_flow_release` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_binding.created_by` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `chk.approval_flow_binding.revision` CHECK (`revision` > 0)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_binding_head` (
                `id` BINARY(16) NOT NULL,
                `business_type` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `latest_revision` INT UNSIGNED NOT NULL,
                `active_revision` INT UNSIGNED NULL,
                `version` INT UNSIGNED NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_binding_head.type` (`business_type`),
                CONSTRAINT `fk.approval_flow_binding_head.active` FOREIGN KEY (`business_type`, `active_revision`) REFERENCES `approval_flow_binding` (`business_type`, `revision`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_binding_head.revision` CHECK (`active_revision` IS NULL OR (`active_revision` > 0 AND `active_revision` <= `latest_revision`)),
                CONSTRAINT `chk.approval_flow_binding_head.activation` CHECK ((`active_revision` IS NULL AND `version` = 0) OR (`active_revision` IS NOT NULL AND `version` > 0))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
