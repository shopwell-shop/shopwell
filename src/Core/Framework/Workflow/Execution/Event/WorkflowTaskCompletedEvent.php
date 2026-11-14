<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * 一条任务完成（同意/拒绝）。
 *
 * 注意这是**单条任务**的完成，不是节点的完成：会签 3 人时它触发 3 次。
 * "整个节点通过了"要看 {@see WorkflowInstanceEndedEvent}，或者监听器自己查聚合结果。
 */
#[Package('framework')]
final class WorkflowTaskCompletedEvent extends Event
{
    final public const EVENT_NAME = 'shopwell.workflow.execution.task.completed';

    public function __construct(
        private readonly string $instanceId,
        private readonly string $taskId,
        private readonly string $nodeId,
        private readonly string $assigneeId,
        private readonly bool $agreed,
        private readonly ?string $comment,
    ) {
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getAssigneeId(): string
    {
        return $this->assigneeId;
    }

    public function isAgreed(): bool
    {
        return $this->agreed;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }
}
