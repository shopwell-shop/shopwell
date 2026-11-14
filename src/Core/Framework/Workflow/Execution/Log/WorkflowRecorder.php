<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Log;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;

/**
 * @internal
 *
 * 归档与日志的唯一写入口。
 *
 * 抽出来的原因：归档（`workflow_task_history`）与日志（`workflow_log`）是**两张必须同时写**的表。
 * 散在引擎、服务、监听器里各写一次，迟早出现"有归档没日志"或"日志说同意了、
 * 归档里没有这条"的裂痕，而这两张表正是审计时唯一能看的证据。
 *
 * 将来加退回、加签、转办时，只在这里加方法（`recordReturn()` 等），
 * 不改调用方的结构。
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
     * 任务完成：归档一条 + 日志一条。
     *
     * `decision` 用动作名（agree/reject），不是任务状态（agreed/rejected）：
     * 归档回答的是"他做了什么"，任务状态回答的是"现在是什么"。
     */
    public function recordTaskCompletion(WorkflowTaskEntity $task, bool $agreed, ?string $comment, Context $context): void
    {
        $decision = $agreed ? 'agree' : 'reject';

        $this->hisTaskRepository->create([
            [
                'id' => Uuid::randomHex(),
                'taskId' => $task->id,
                'instanceId' => $task->instanceId,
                'nodeId' => $task->nodeId,
                'nodeName' => $task->nodeName,
                'assigneeId' => $task->assigneeId,
                'countersignType' => $task->countersignType,
                'decision' => $decision,
                // 拒绝必须写意见：校验在 ApprovalWorkflowService，这里再做一次兜底会掩盖调用方的疏漏，
                // 所以只在为空时存 null，不抛异常
                'comment' => $comment,
                'completedAt' => $this->clock->now(),
            ],
        ], $context);

        $this->record($task->instanceId, $decision, $task->id, $task->assigneeId, $comment, [], $context);
    }

    /**
     * 普通日志（发起、撤销、抄送等）。
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
