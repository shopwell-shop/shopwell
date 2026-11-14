<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * 流程发起成功。
 *
 * 事件在**事务提交后**才派发（见 {@see \Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue}），
 * 所以监听器里读库能读到刚写的实例；同时它发短信、推企微也不会因为回滚而"发出去了但单子没建"。
 *
 * 监听器里**不要**再调 WorkflowService 的公共方法（会打乱事件队列边界、再开一层事务），
 * 需要复用逻辑请调更内层的方法。
 */
#[Package('framework')]
final class WorkflowStartedEvent extends Event
{
    final public const EVENT_NAME = 'shopwell.workflow.execution.started';

    public function __construct(
        private readonly string $instanceId,
        private readonly string $definitionId,
        private readonly string $initiatorId,
        private readonly string $title,
    ) {
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getDefinitionId(): string
    {
        return $this->definitionId;
    }

    public function getInitiatorId(): string
    {
        return $this->initiatorId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
