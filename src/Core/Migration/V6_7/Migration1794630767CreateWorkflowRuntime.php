<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\Migration\Traits\StateMachineMigration;
use Shopwell\Core\Migration\Traits\StateMachineMigrationTrait;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794630767CreateWorkflowRuntime extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1794630767;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_execution_definition` (
    `id` BINARY(16) NOT NULL,
    `code` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `description` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
    `status` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `is_latest` TINYINT(1) NOT NULL DEFAULT 1,
    `graph_data` JSON NULL,
    `form_schema` JSON NULL,
    `ext` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.workflow_execution_definition.code` (`code`),
    KEY `workflow_execution_definition.status_idx` (`status`, `is_latest`),
    CONSTRAINT `json.workflow_execution_definition.graph_data` CHECK (JSON_VALID(`graph_data`)),
    CONSTRAINT `json.workflow_execution_definition.form_schema` CHECK (JSON_VALID(`form_schema`)),
    CONSTRAINT `json.workflow_execution_definition.ext` CHECK (JSON_VALID(`ext`))
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_instance` (
    `id` BINARY(16) NOT NULL,
    `state_id` BINARY(16) NOT NULL,
    `number` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `definition_id` BINARY(16) NOT NULL,
    `definition_version` INT NOT NULL DEFAULT 1,
    `title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `initiator_id` BINARY(16) NOT NULL,
    `form_data` JSON NULL,
    `graph_snapshot` JSON NULL,
    `current_node_id` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL,
    `round` INT NOT NULL DEFAULT 1,
    `started_at` DATETIME(3) NULL,
    `ended_at` DATETIME(3) NULL,
    `ext` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.workflow_instance.number` (`number`),
    KEY `workflow_instance.state_id_idx` (`state_id`),
    KEY `workflow_instance.definition_id_idx` (`definition_id`),
    KEY `workflow_instance.initiator_id_idx` (`initiator_id`, `created_at`),
    CONSTRAINT `json.workflow_instance.form_data` CHECK (JSON_VALID(`form_data`)),
    CONSTRAINT `json.workflow_instance.graph_snapshot` CHECK (JSON_VALID(`graph_snapshot`)),
    CONSTRAINT `json.workflow_instance.ext` CHECK (JSON_VALID(`ext`)),
    CONSTRAINT `fk.workflow_instance.state_id` FOREIGN KEY (`state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_instance.definition_id` FOREIGN KEY (`definition_id`)
        REFERENCES `workflow_execution_definition` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_instance.initiator_id` FOREIGN KEY (`initiator_id`)
        REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_task` (
    `id` BINARY(16) NOT NULL,
    `state_id` BINARY(16) NOT NULL,
    `instance_id` BINARY(16) NOT NULL,
    `node_id` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `node_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `countersign_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `round` INT NOT NULL DEFAULT 1,
    `sequence` INT NOT NULL DEFAULT 1,
    `assignee_id` BINARY(16) NOT NULL,
    `assignee_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `completed_at` DATETIME(3) NULL,
    `ext` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `workflow_task.node_idx` (`instance_id`, `node_id`, `round`),
    KEY `workflow_task.assignee_idx` (`assignee_id`, `state_id`, `created_at`),
    KEY `workflow_task.state_id_idx` (`state_id`),
    CONSTRAINT `json.workflow_task.ext` CHECK (JSON_VALID(`ext`)),
    CONSTRAINT `fk.workflow_task.state_id` FOREIGN KEY (`state_id`)
        REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_task.instance_id` FOREIGN KEY (`instance_id`)
        REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_task.assignee_id` FOREIGN KEY (`assignee_id`)
        REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_task_history` (
    `id` BINARY(16) NOT NULL,
    `task_id` BINARY(16) NOT NULL,
    `instance_id` BINARY(16) NOT NULL,
    `node_id` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `node_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `assignee_id` BINARY(16) NOT NULL,
    `countersign_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `decision` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    `comment` TEXT COLLATE utf8mb4_unicode_ci NULL,
    `completed_at` DATETIME(3) NULL,
    `ext` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `workflow_task_history.instance_idx` (`instance_id`, `completed_at`),
    KEY `workflow_task_history.assignee_idx` (`assignee_id`, `completed_at`),
    KEY `workflow_task_history.task_id_idx` (`task_id`),
    CONSTRAINT `json.workflow_task_history.ext` CHECK (JSON_VALID(`ext`)),
    CONSTRAINT `fk.workflow_task_history.task_id` FOREIGN KEY (`task_id`)
        REFERENCES `workflow_task` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_task_history.instance_id` FOREIGN KEY (`instance_id`)
        REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_task_history.assignee_id` FOREIGN KEY (`assignee_id`)
        REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_log` (
    `id` BINARY(16) NOT NULL,
    `instance_id` BINARY(16) NOT NULL,
    `task_id` BINARY(16) NULL,
    `operator_id` BINARY(16) NULL,
    `action` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `log_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `comment` TEXT COLLATE utf8mb4_unicode_ci NULL,
    `payload` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    KEY `workflow_log.instance_idx` (`instance_id`, `created_at`),
    CONSTRAINT `json.workflow_log.payload` CHECK (JSON_VALID(`payload`)),
    CONSTRAINT `fk.workflow_log.instance_id` FOREIGN KEY (`instance_id`)
        REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_log.operator_id` FOREIGN KEY (`operator_id`)
        REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL);

        $connection->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `workflow_copy` (
    `id` BINARY(16) NOT NULL,
    `instance_id` BINARY(16) NOT NULL,
    `node_id` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_id` BINARY(16) NOT NULL,
    `read_at` DATETIME(3) NULL,
    `ext` JSON NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.workflow_copy.node_user` (`instance_id`, `node_id`, `user_id`),
    KEY `workflow_copy.user_idx` (`user_id`, `read_at`),
    CONSTRAINT `json.workflow_copy.ext` CHECK (JSON_VALID(`ext`)),
    CONSTRAINT `fk.workflow_copy.instance_id` FOREIGN KEY (`instance_id`)
        REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.workflow_copy.user_id` FOREIGN KEY (`user_id`)
        REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL);

        $this->import(new StateMachineMigration(
            WorkflowInstanceStates::STATE_MACHINE,
            '审批流程',
            'Workflow approval',
            [
                StateMachineMigration::state(WorkflowInstanceStates::RUNNING, '审批中', 'In progress'),
                StateMachineMigration::state(WorkflowInstanceStates::AGREED, '已通过', 'Approved'),
                StateMachineMigration::state(WorkflowInstanceStates::REJECTED, '已拒绝', 'Rejected'),
                StateMachineMigration::state(WorkflowInstanceStates::CANCELED, '已撤销', 'Canceled'),
            ],
            [
                StateMachineMigration::transition('agree', 'running', 'agreed'),
                StateMachineMigration::transition('reject', 'running', 'rejected'),
                StateMachineMigration::transition('cancel', 'running', 'canceled'),
            ],
            WorkflowInstanceStates::RUNNING,
        ), $connection);
        $this->import(new StateMachineMigration(
            WorkflowTaskStates::STATE_MACHINE,
            '审批任务',
            'Workflow task',
            [
                StateMachineMigration::state(WorkflowTaskStates::TODO, '待审批', 'Pending'),
                StateMachineMigration::state(WorkflowTaskStates::AGREED, '已同意', 'Agreed'),
                StateMachineMigration::state(WorkflowTaskStates::REJECTED, '已拒绝', 'Rejected'),
                StateMachineMigration::state(WorkflowTaskStates::CANCELED, '已取消', 'Canceled'),
            ],
            [
                StateMachineMigration::transition('agree', 'todo', 'agreed'),
                StateMachineMigration::transition('reject', 'todo', 'rejected'),
                StateMachineMigration::transition('cancel', 'todo', 'canceled'),
            ],
            WorkflowTaskStates::TODO,
        ), $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
