<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Log;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;

/**
 * Write task history and action logs together to preserve a consistent audit trail.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowRecorder
{
    /**
     * @param EntityRepository<WorkflowTaskHistoryCollection> $hisTaskRepository
     * @param EntityRepository<WorkflowLogCollection> $logRepository
     */
    public function __construct(
        private readonly EntityRepository $hisTaskRepository,
        private readonly EntityRepository $logRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Archive task completion and log its decision using action names, not task state names.
     */
    public function recordTaskCompletion(WorkflowTaskEntity $task, bool $agreed, ?string $comment, Context $context): void
    {
        $this->recordTaskAction($task, $agreed ? 'agree' : 'reject', $comment, [], $context);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordTaskAction(WorkflowTaskEntity $task, string $decision, ?string $comment, array $payload, Context $context): void
    {
        $this->hisTaskRepository->create([
            [
                'id' => Uuid::randomHex(),
                'taskId' => $task->id,
                'instanceId' => $task->instanceId,
                'nodeId' => $task->nodeId,
                'round' => $task->round,
                'nodeName' => $task->nodeName,
                'assigneeId' => $task->assigneeId,
                'countersignType' => $task->countersignType,
                'decision' => $decision,
                // Required comments are validated before archiving the action.
                'comment' => $comment,
                'completedAt' => $this->clock->now(),
                'ext' => $payload === [] ? null : $payload,
            ],
        ], $context);

        $this->record($task->instanceId, $decision, $task->id, $task->assigneeId, $comment, [...$payload, 'round' => $task->round], $context);
    }

    /**
     * Record an action such as starting, canceling or copying.
     *
     * @param array<string, mixed> $payload
     */
    public function record(
        string $instanceId,
        string $action,
        ?string $taskId,
        ?string $operatorId,
        ?string $comment,
        array $payload,
        Context $context,
    ): void {
        $this->logRepository->create([
            [
                'id' => Uuid::randomHex(),
                'instanceId' => $instanceId,
                'taskId' => $taskId,
                'operatorId' => $operatorId,
                'action' => $action,
                'logType' => WorkflowLogTypes::NORMAL,
                'comment' => $comment,
                'payload' => $payload === [] ? null : $payload,
            ],
        ], $context);
    }
}
