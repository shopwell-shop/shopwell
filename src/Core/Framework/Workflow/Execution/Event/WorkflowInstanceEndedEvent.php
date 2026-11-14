<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * 实例结束（通过或拒绝）。
 *
 * 这是**唯一**能确定"这张单走完了"的事件。所有收尾动作挂这里：
 * 回写业务单据状态、通知发起人、清理未处理完的待办。
 *
 * 将来的超时、代理不需要新事件，监听这个 + 查实例即可。
 */
#[Package('framework')]
final class WorkflowInstanceEndedEvent extends Event
{
    final public const EVENT_NAME = 'shopwell.workflow.execution.instance.ended';

    public function __construct(
        private readonly string $instanceId,
        private readonly string $result,
    ) {
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getResult(): string
    {
        return $this->result;
    }
}
