<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * 一条待办被创建。
 *
 * 通知（站内信/短信/企微）挂这个事件，**不要**挂在"进入节点"上：
 * 会签一次建 N 条任务，通知要按人发；顺序会签后续任务是逐条建的，
 * 只有挂在"任务创建"上才能保证每个轮到的人都被通知到。
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
