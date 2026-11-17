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
class Migration1794838402AddWorkflowResubmission extends MigrationStep
{
    use StateMachineMigrationTrait;

    public function getCreationTimestamp(): int
    {
        return 1794838402;
    }

    public function update(Connection $connection): void
    {
        foreach (['workflow_copy', 'workflow_task_history'] as $table) {
            $this->addColumn($connection, $table, 'round', 'INT', nullable: false, default: '1');
        }
        $table = $connection->createSchemaManager()->introspectTableByUnquotedName('workflow_copy');
        if ($table->hasIndex('uniq.workflow_copy.node_user')) {
            $connection->executeStatement('ALTER TABLE `workflow_copy` DROP INDEX `uniq.workflow_copy.node_user`, ADD UNIQUE INDEX `uniq.workflow_copy.round_node_user` (`instance_id`, `round`, `node_id`, `user_id`)');
        }

        $this->import(new StateMachineMigration(
            WorkflowInstanceStates::STATE_MACHINE,
            '工作流',
            'Workflow',
            [StateMachineMigration::state(WorkflowInstanceStates::RETURNED, '待修改', 'Awaiting changes')],
            [
                StateMachineMigration::transition(WorkflowInstanceStates::ACTION_RETURN, WorkflowInstanceStates::RUNNING, WorkflowInstanceStates::RETURNED),
                StateMachineMigration::transition(WorkflowInstanceStates::ACTION_RESUBMIT, WorkflowInstanceStates::RETURNED, WorkflowInstanceStates::RUNNING),
                StateMachineMigration::transition(WorkflowInstanceStates::ACTION_CANCEL, WorkflowInstanceStates::RETURNED, WorkflowInstanceStates::CANCELED),
            ],
            WorkflowInstanceStates::RUNNING,
        ), $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
