<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionCollection;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Commits one personal decision and its group result under the caller's instance lock and transaction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalNodeProgressionTest
 */
#[Package('framework')]
class ApprovalTaskDecisionStore
{
    /**
     * @param EntityRepository<ApprovalTaskCollection> $tasks
     * @param EntityRepository<ApprovalNodeExecutionCollection> $executions
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $tasks,
        private readonly EntityRepository $executions,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
        private readonly ApprovalDecisionEvaluator $evaluator,
        private readonly ApprovalExecutionStore $executionStore,
    ) {
    }

    public function requireAssignee(string $instanceId, string $taskId, string $userId): void
    {
        $task = $this->readTask($instanceId, $taskId);
        if ($task['assignee_id'] !== Uuid::fromHexToBytes($userId)) {
            throw ApprovalFlowException::taskAssigneeRequired();
        }
        $active = $this->connection->fetchOne(
            'SELECT active FROM `user` WHERE id = :id LOCK IN SHARE MODE',
            ['id' => Uuid::fromHexToBytes($userId)],
        );
        if (!$active) {
            throw ApprovalFlowException::taskAssigneeRequired();
        }
    }

    public function decide(string $instanceId, string $taskId, string $commandId, ApprovalOutcome $decision, Context $context): void
    {
        $task = $this->readTask($instanceId, $taskId);
        if (ApprovalTaskStatus::from($task['status']) !== ApprovalTaskStatus::Pending) {
            throw ApprovalFlowException::taskNotPending($taskId);
        }
        $executionId = Uuid::fromBytesToHex($task['execution_id']);
        $this->executionStore->requireActiveExecution($instanceId, $executionId);
        if (ApprovalNodeExecutionStatus::from($task['execution_status']) !== ApprovalNodeExecutionStatus::AwaitingApproval || ($task['approval_policy'] === null || $task['approval_policy_version'] === null)) {
            throw ApprovalFlowException::invalidApprovalEntry($executionId, 'The execution is not awaiting an approval group.');
        }
        $this->requireVisibleTask($taskId);
        $group = $this->connection->fetchAllAssociative(
            'SELECT id, status FROM approval_flow_task WHERE execution_id = :execution ORDER BY id FOR UPDATE',
            ['execution' => $task['execution_id']],
        );
        $decisionStatus = match ($decision) {
            ApprovalOutcome::Approved => ApprovalTaskStatus::Approved,
            ApprovalOutcome::Rejected => ApprovalTaskStatus::Rejected,
        };
        $statuses = [$decisionStatus];
        foreach ($group as $member) {
            if ($member['id'] !== Uuid::fromHexToBytes($taskId)) {
                $statuses[] = ApprovalTaskStatus::from($member['status']);
            }
        }
        $outcome = $this->evaluator->evaluate(Uuid::fromBytesToHex($task['release_id']), $task['approval_policy'], $task['approval_policy_version'], $statuses);
        $updates = [['id' => $taskId, 'status' => $decisionStatus->value, 'completionCommandId' => $commandId, 'completionReason' => ApprovalTaskCompletionReason::DECISION]];
        if ($outcome !== null) {
            foreach ($group as $member) {
                if ($member['id'] !== Uuid::fromHexToBytes($taskId) && ApprovalTaskStatus::from($member['status']) === ApprovalTaskStatus::Pending) {
                    $updates[] = ['id' => Uuid::fromBytesToHex($member['id']), 'status' => ApprovalTaskStatus::Cancelled->value, 'completionCommandId' => $commandId, 'completionReason' => ApprovalTaskCompletionReason::GROUP_RESOLVED];
                }
            }
        }
        $this->writeGuard->write($context, function (Context $writeContext) use ($updates): void {
            $this->tasks->update($updates, $writeContext);
        });
        if ($outcome !== null) {
            $this->writeGuard->write($context, function (Context $writeContext) use ($executionId, $outcome): void {
                $this->executions->update([[
                    'id' => $executionId,
                    'status' => ApprovalNodeExecutionStatus::Completed->value,
                    'outcome' => $outcome->value,
                ]], $writeContext);
            });
        }
    }

    /**
     * @return array{assignee_id: string, execution_id: string, status: string, execution_status: string, approval_policy: ?string, approval_policy_version: ?string, release_id: string}
     */
    private function readTask(string $instanceId, string $taskId): array
    {
        $task = $this->connection->fetchAssociative(
            'SELECT task.assignee_id, task.execution_id, task.status, execution.status AS execution_status, execution.approval_policy, execution.approval_policy_version, instance.release_id
             FROM approval_flow_task task INNER JOIN approval_flow_execution execution ON execution.id = task.execution_id
             INNER JOIN approval_flow_instance instance ON instance.id = execution.instance_id
             WHERE task.id = :task AND execution.instance_id = :instance FOR UPDATE',
            ['task' => Uuid::fromHexToBytes($taskId), 'instance' => Uuid::fromHexToBytes($instanceId)],
        );

        if ($task === false) {
            throw ApprovalFlowException::taskNotFound($taskId);
        }

        return [
            'assignee_id' => $task['assignee_id'],
            'execution_id' => $task['execution_id'],
            'status' => $task['status'],
            'execution_status' => $task['execution_status'],
            'approval_policy' => $task['approval_policy'],
            'approval_policy_version' => $task['approval_policy_version'],
            'release_id' => $task['release_id'],
        ];
    }

    private function requireVisibleTask(string $taskId): void
    {
        if ($this->connection->getTransactionNestingLevel() > 1 && $this->connection->fetchOne(
            'SELECT 1 FROM approval_flow_task WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($taskId)],
        ) === false) {
            throw ApprovalFlowException::taskOutsideSnapshot($taskId);
        }
    }
}
