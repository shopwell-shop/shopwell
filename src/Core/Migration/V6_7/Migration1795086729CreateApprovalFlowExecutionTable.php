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
            CREATE TABLE IF NOT EXISTS `approval_flow_execution` (
                `id` BINARY(16) NOT NULL,
                `instance_id` BINARY(16) NOT NULL,
                `node_id` LONGTEXT NOT NULL,
                `incoming_connection_id` LONGTEXT NULL,
                `sequence` INT UNSIGNED NOT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `approval_policy` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `outcome` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_execution.sequence` (`instance_id`, `sequence`),
                CONSTRAINT `fk.approval_flow_execution.instance` FOREIGN KEY (`instance_id`) REFERENCES `approval_flow_instance` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_execution.sequence` CHECK (`sequence` > 0),
                CONSTRAINT `chk.approval_flow_execution.status` CHECK (`status` IN ('waiting', 'awaiting_approval', 'completed')),
                CONSTRAINT `chk.approval_flow_execution.policy` CHECK (`approval_policy` IN ('core:single', 'core:all', 'core:any')),
                CONSTRAINT `chk.approval_flow_execution.outcome` CHECK (`outcome` IN ('approved', 'rejected'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }
}
