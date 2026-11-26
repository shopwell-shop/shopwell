<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795086729CreateApprovalFlowExecutionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795086729;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_execution_scope` (
                `id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `generation` INT UNSIGNED NOT NULL,
                `join_node_id` LONGTEXT NOT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.approval_flow_execution_scope.instance` (`instance_id`, `status`),
                CONSTRAINT `fk.approval_flow_execution_scope.instance` FOREIGN KEY (`instance_id`) REFERENCES `approval_flow_instance` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_execution_scope.parent` FOREIGN KEY (`parent_id`) REFERENCES `approval_flow_execution_scope` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_execution_scope.generation` CHECK (`generation` > 0),
                CONSTRAINT `chk.approval_flow_execution_scope.status` CHECK (`status` IN ('open', 'completed', 'cancelled'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_execution_branch` (
                `id` BINARY(16) NOT NULL,
                `scope_id` BINARY(16) NOT NULL,
                `connection_id` LONGTEXT NOT NULL,
                `connection_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `arrival_connection_id` LONGTEXT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_execution_branch.connection` (`scope_id`, `connection_hash`),
                CONSTRAINT `fk.approval_flow_execution_branch.scope` FOREIGN KEY (`scope_id`) REFERENCES `approval_flow_execution_scope` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_execution_branch.status` CHECK (`status` IN ('pending', 'arrived', 'cancelled'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_execution` (
                `id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `node_id` LONGTEXT NOT NULL,
                `incoming_connection_id` LONGTEXT NULL,
                `sequence` INT UNSIGNED NOT NULL,
                `scope_id` BINARY(16) NULL,
                `branch_id` BINARY(16) NULL,
                `opened_scope_id` BINARY(16) NULL,
                `waiting_scope_id` BINARY(16) NULL,
                `return_target_id` BINARY(16) NULL,
                `generation` INT UNSIGNED NOT NULL DEFAULT 1,
                `advanced` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `approval_policy` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `approval_policy_version` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `outcome` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_execution.sequence` (`instance_id`, `sequence`),
                CONSTRAINT `fk.approval_flow_execution.instance` FOREIGN KEY (`instance_id`) REFERENCES `approval_flow_instance` (`id`) ON DELETE RESTRICT,
                UNIQUE KEY `uniq.approval_flow_execution.opened_scope` (`opened_scope_id`),
                UNIQUE KEY `uniq.approval_flow_execution.waiting_scope` (`waiting_scope_id`),
                KEY `idx.approval_flow_execution.ready` (`instance_id`, `advanced`, `status`),
                CONSTRAINT `fk.approval_flow_execution.scope` FOREIGN KEY (`scope_id`) REFERENCES `approval_flow_execution_scope` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_execution.branch` FOREIGN KEY (`branch_id`) REFERENCES `approval_flow_execution_branch` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_execution.opened_scope` FOREIGN KEY (`opened_scope_id`) REFERENCES `approval_flow_execution_scope` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_execution.return_target` FOREIGN KEY (`return_target_id`) REFERENCES `approval_flow_execution` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk.approval_flow_execution.waiting_scope` FOREIGN KEY (`waiting_scope_id`) REFERENCES `approval_flow_execution_scope` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_execution.generation` CHECK (`generation` > 0),
                CONSTRAINT `chk.approval_flow_execution.sequence` CHECK (`sequence` > 0),
                CONSTRAINT `chk.approval_flow_execution.status` CHECK (`status` IN ('waiting', 'awaiting_approval', 'awaiting_signal', 'completed', 'cancelled')),
                CONSTRAINT `chk.approval_flow_execution.outcome` CHECK (`outcome` IN ('approved', 'rejected'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
