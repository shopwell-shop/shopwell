<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1795087960CreateApprovalFlowTaskTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795087960;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `approval_flow_task` (
                `id` BINARY(16) NOT NULL,
                `execution_id` BINARY(16) NOT NULL,
                `assignee_id` BINARY(16) NOT NULL,
                `status` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `completion_command_id` BINARY(16) NULL,
                `completion_reason` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.approval_flow_task.assignee` (`execution_id`, `assignee_id`),
                CONSTRAINT `fk.approval_flow_task.execution` FOREIGN KEY (`execution_id`) REFERENCES `approval_flow_execution` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `chk.approval_flow_task.status` CHECK (`status` IN ('pending', 'approved', 'rejected', 'cancelled'))
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci;
        SQL);
    }
}
