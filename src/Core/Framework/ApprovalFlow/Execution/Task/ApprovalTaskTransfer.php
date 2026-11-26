<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Replaces one assignment within the same execution under the instance command lock.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api\ApprovalTaskControllerTest
 */
#[Package('framework')]
class ApprovalTaskTransfer
{
    private const ELIGIBLE_USERS = <<<'SQL'
        FROM `user` u
        WHERE u.active = 1
          AND (u.admin = 1 OR EXISTS (
              SELECT 1
              FROM acl_user_role membership
              INNER JOIN acl_role role ON role.id = membership.acl_role_id
              WHERE membership.user_id = u.id
              GROUP BY membership.user_id
              HAVING MAX(JSON_CONTAINS(role.privileges, JSON_QUOTE(:view))) = 1
                 AND MAX(JSON_CONTAINS(role.privileges, JSON_QUOTE(:decide))) = 1
          ))
          AND NOT EXISTS (
              SELECT 1 FROM approval_flow_task task
              WHERE task.execution_id = :execution AND task.assignee_id = u.id
                AND NOT (task.completion_reason <=> :transferred)
          )
        SQL;

    /**
     * @param EntityRepository<ApprovalTaskCollection> $tasks
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ApprovalTaskDecisionStore $decisions,
        private readonly EntityRepository $tasks,
        private readonly ApprovalInstanceWriteGuard $writeGuard,
    ) {
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function candidates(string $instanceId, string $taskId, string $term): array
    {
        $execution = $this->decisions->pendingExecution($instanceId, $taskId);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.id, u.name ' . self::ELIGIBLE_USERS . ' AND LOCATE(:term, u.name) > 0 ORDER BY u.name, u.id LIMIT 20',
            [...$this->parameters($execution->id), 'term' => $term],
        );

        return array_map(static fn (array $row): array => ['id' => Uuid::fromBytesToHex($row['id']), 'name' => $row['name']], $rows);
    }

    public function transfer(string $instanceId, string $taskId, string $commandId, string $assigneeId, Context $context): void
    {
        $execution = $this->decisions->pendingExecution($instanceId, $taskId);
        $eligible = $this->connection->fetchOne(
            'SELECT u.id ' . self::ELIGIBLE_USERS . ' AND u.id = :assignee LOCK IN SHARE MODE',
            [...$this->parameters($execution->id), 'assignee' => Uuid::fromHexToBytes($assigneeId)],
        );
        if ($eligible === false) {
            throw ApprovalFlowException::transferAssigneeUnavailable($assigneeId);
        }

        $this->writeGuard->write($context, function (Context $writeContext) use ($taskId, $commandId): void {
            $this->tasks->update([[
                'id' => $taskId,
                'status' => ApprovalTaskStatus::Cancelled->value,
                'completionCommandId' => $commandId,
                'completionReason' => ApprovalTaskCompletionReason::TRANSFERRED,
            ]], $writeContext);
        });
        $this->writeGuard->write($context, function (Context $writeContext) use ($execution, $assigneeId, $taskId): void {
            $this->tasks->create([[
                'id' => Uuid::randomHex(),
                'executionId' => $execution->id,
                'assigneeId' => $assigneeId,
                'replacesTaskId' => $taskId,
                'status' => ApprovalTaskStatus::Pending->value,
            ]], $writeContext);
        });
    }

    /**
     * @return array{execution: string, transferred: string, view: string, decide: string}
     */
    private function parameters(string $executionId): array
    {
        return [
            'execution' => Uuid::fromHexToBytes($executionId),
            'transferred' => ApprovalTaskCompletionReason::TRANSFERRED,
            'view' => 'approval_flow:access',
            'decide' => ApprovalInstanceService::DECIDE_PRIVILEGE,
        ];
    }
}
