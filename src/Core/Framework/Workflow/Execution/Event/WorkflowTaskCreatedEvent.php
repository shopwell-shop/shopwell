<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A task was created; use this event for per-assignee notifications, including sequential approval.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowTaskCreatedEvent extends Event
{
    final public const EVENT_NAME = 'shopwell.workflow.execution.task.created';

    public function __construct(
        private readonly string $taskId,
        private readonly string $instanceId,
        private readonly string $nodeId,
        private readonly string $nodeName,
        private readonly string $assigneeId,
        private readonly string $countersignType,
    ) {
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function getNodeName(): string
    {
        return $this->nodeName;
    }

    public function getAssigneeId(): string
    {
        return $this->assigneeId;
    }

    public function getCountersignType(): string
    {
        return $this->countersignType;
    }
}
