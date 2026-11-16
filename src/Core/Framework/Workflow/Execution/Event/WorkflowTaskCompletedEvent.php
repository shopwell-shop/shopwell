<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A single task was approved or rejected; this does not imply that the node or instance is complete.
 *
 * @internal
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
