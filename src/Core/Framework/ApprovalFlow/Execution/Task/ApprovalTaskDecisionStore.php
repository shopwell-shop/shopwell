<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalExecutionState;
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
 * @phpstan-type GroupTask array{id: string, status: ApprovalTaskStatus, isPreApproval: bool}
 * @phpstan-type TaskRow array{assignee_id: string, execution_id: string, status: string, release_id: string, blocked_task_id: ?string}
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

    /**
     * @return array{execution: ApprovalExecutionState, blockedTaskId: ?string}
     */
    public function pendingTask(string $instanceId, string $taskId): array
    {
        $task = $this->readTask($instanceId, $taskId);

        return [
            'execution' => $this->requirePendingExecution($instanceId, $taskId, $task),
            'blockedTaskId' => $task['blocked_task_id'] === null ? null : Uuid::fromBytesToHex($task['blocked_task_id']),
        ];
    }

    public function pendingPrimaryExecution(string $instanceId, string $taskId): ApprovalExecutionState
    {
        $task = $this->pendingTask($instanceId, $taskId);
        if ($task['blockedTaskId'] !== null) {
            throw ApprovalFlowException::primaryTaskRequired($taskId);
        }

        return $task['execution'];
    }

    public function decide(string $instanceId, string $taskId, string $commandId, ApprovalOutcome $decision, Context $context): void
    {
        $task = $this->readTask($instanceId, $taskId);
        $execution = $this->requirePendingExecution($instanceId, $taskId, $task);
        $decisionStatus = match ($decision) {
            ApprovalOutcome::Approved => ApprovalTaskStatus::Approved,
            ApprovalOutcome::Rejected => ApprovalTaskStatus::Rejected,
        };
        $updates = [[
            'id' => $taskId,
            'status' => $decisionStatus->value,
            'completionCommandId' => $commandId,
            'completionReason' => ApprovalTaskCompletionReason::DECISION,
        ]];

        $blockedTaskId = $task['blocked_task_id'];
        if ($blockedTaskId !== null && $decision === ApprovalOutcome::Approved) {
            $updates[] = ['id' => Uuid::fromBytesToHex($blockedTaskId), 'status' => ApprovalTaskStatus::Pending->value];
            $this->saveTasks($updates, $context);

            return;
        }

        $group = $this->group($task['execution_id']);
        $outcome = $blockedTaskId !== null
            ? ApprovalOutcome::Rejected
            : $this->evaluateGroup($execution, Uuid::fromBytesToHex($task['release_id']), $taskId, $decisionStatus, $group);
        if ($outcome !== null) {
            $updates = [...$updates, ...$this->closeGroupTasks($group, $taskId, $commandId)];
        }
        $this->saveTasks($updates, $context);

        if ($outcome !== null) {
            $this->writeGuard->write($context, function (Context $writeContext) use ($execution, $outcome): void {
                $this->executions->update([[
                    'id' => $execution->id,
                    'status' => ApprovalNodeExecutionStatus::Completed->value,
                    'outcome' => $outcome->value,
                ]], $writeContext);
            });
        }
    }

    /**
     * @return list<GroupTask>
     */
    private function group(string $executionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, status, blocked_task_id FROM approval_flow_task
             WHERE execution_id = :execution AND NOT (completion_reason <=> :transferred) ORDER BY id FOR UPDATE',
            ['execution' => $executionId, 'transferred' => ApprovalTaskCompletionReason::TRANSFERRED],
        );

        return array_map(static fn (array $row): array => [
            'id' => Uuid::fromBytesToHex($row['id']),
            'status' => ApprovalTaskStatus::from($row['status']),
            'isPreApproval' => $row['blocked_task_id'] !== null,
        ], $rows);
    }

    /**
     * @param list<GroupTask> $group
     */
    private function evaluateGroup(ApprovalExecutionState $execution, string $releaseId, string $taskId, ApprovalTaskStatus $decision, array $group): ?ApprovalOutcome
    {
        $policy = $execution->approvalPolicy;
        $version = $execution->approvalPolicyVersion;
        if ($policy === null || $version === null) {
            throw ApprovalFlowException::invalidApprovalEntry($execution->id, 'The execution is missing its approval policy.');
        }

        $statuses = [$decision];
        $hasPreApproval = false;
        foreach ($group as $member) {
            if ($member['id'] === $taskId || $member['isPreApproval']) {
                continue;
            }

            $status = $member['status'];
            if ($status === ApprovalTaskStatus::Blocked) {
                $hasPreApproval = true;
                $status = ApprovalTaskStatus::Pending;
            }

            $statuses[] = $status;
        }
        $outcome = $this->evaluator->evaluate($releaseId, $policy, $version, $statuses);

        // The group can still veto while a preliminary review is open.
        return $hasPreApproval && $outcome === ApprovalOutcome::Approved ? null : $outcome;
    }

    /**
     * @param list<GroupTask> $group
     *
     * @return list<array{id: string, status: string, completionCommandId: string, completionReason: string}>
     */
    private function closeGroupTasks(array $group, string $decidedTaskId, string $commandId): array
    {
        $updates = [];
        foreach ($group as $member) {
            if ($member['id'] === $decidedTaskId || !$member['status']->isOpen()) {
                continue;
            }

            $updates[] = [
                'id' => $member['id'],
                'status' => ApprovalTaskStatus::Cancelled->value,
                'completionCommandId' => $commandId,
                'completionReason' => ApprovalTaskCompletionReason::GROUP_RESOLVED,
            ];
        }

        return $updates;
    }

    /**
     * @param list<array<string, string>> $updates
     */
    private function saveTasks(array $updates, Context $context): void
    {
        $this->writeGuard->write($context, function (Context $writeContext) use ($updates): void {
            $this->tasks->update($updates, $writeContext);
        });
    }

    /**
     * @param TaskRow $task
     */
    private function requirePendingExecution(string $instanceId, string $taskId, array $task): ApprovalExecutionState
    {
        if (ApprovalTaskStatus::from($task['status']) !== ApprovalTaskStatus::Pending) {
            throw ApprovalFlowException::taskNotPending($taskId);
        }
        $executionId = Uuid::fromBytesToHex($task['execution_id']);
        $execution = $this->executionStore->execution($instanceId, $executionId);
        $this->executionStore->requireActive($instanceId, $execution);
        if ($execution->status !== ApprovalNodeExecutionStatus::AwaitingApproval) {
            throw ApprovalFlowException::invalidApprovalEntry($executionId, 'The execution is not awaiting an approval group.');
        }
        $this->requireVisibleTask($taskId);

        return $execution;
    }

    /**
     * @return TaskRow
     */
    private function readTask(string $instanceId, string $taskId): array
    {
        $task = $this->connection->fetchAssociative(
            'SELECT task.assignee_id, task.execution_id, task.status, task.blocked_task_id, instance.release_id
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
            'release_id' => $task['release_id'],
            'blocked_task_id' => $task['blocked_task_id'],
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
