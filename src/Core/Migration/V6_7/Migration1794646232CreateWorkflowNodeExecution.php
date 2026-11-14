<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794646232CreateWorkflowNodeExecution extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1794646232;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_node_execution` (
    `id` BINARY(16) NOT NULL,
    `instance_id` BINARY(16) NOT NULL,
    `node_id` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `round` INT NOT NULL,
    `sequence` INT NOT NULL,
    `source_connection_id` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL,
    `status` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `waiting_for` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
    `result` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL,
    `output` JSON NULL,
    `started_at` DATETIME(3) NULL,
    `ended_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.workflow_node_execution.sequence` (`instance_id`, `sequence`),
    KEY `workflow_node_execution.instance_status` (`instance_id`, `status`),
    CONSTRAINT `json.workflow_node_execution.output` CHECK (JSON_VALID(`output`)),
    CONSTRAINT `fk.workflow_node_execution.instance_id` FOREIGN KEY (`instance_id`)
        REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SQL);
        if (!$connection->createSchemaManager()->introspectTableByUnquotedName('workflow_instance')->hasColumn('result')) {
            $connection->executeStatement('ALTER TABLE `workflow_instance` ADD COLUMN `result` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL');
        }

        $this->import(new StateMachineMigration(
            WorkflowInstanceStates::STATE_MACHINE,
            '工作流',
            'Workflow',
            [StateMachineMigration::state(WorkflowInstanceStates::COMPLETED, '已完成', 'Completed')],
            [StateMachineMigration::transition(WorkflowInstanceStates::ACTION_COMPLETE, WorkflowInstanceStates::RUNNING, WorkflowInstanceStates::COMPLETED)],
            WorkflowInstanceStates::RUNNING,
        ), $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
