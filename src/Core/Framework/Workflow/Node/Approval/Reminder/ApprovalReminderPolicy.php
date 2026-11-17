<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalReminderPolicy
{
    public function __construct(private ClockInterface $clock, private EventDispatcherInterface $dispatcher)
    {
    }

    public function allows(WorkflowTaskEntity $task, WorkflowInstanceEntity $instance, ApprovalReminderEntity $receipt, string $source, Context $context): bool
    {
        if (!$this->isCurrent($task, $instance) || ($receipt->deliveryId !== null && $receipt->deliveredAt === null)) {
            return false;
        }

        if ($source === ApprovalReminderEntity::MANUAL) {
            $actor = $context->getSource();
            if (!$actor instanceof AdminApiSource || $actor->getUserId() !== $instance->initiatorId || !$context->isAllowed('workflow_instance:start')) {
                return false;
            }
            if ($receipt->lastRemindedAt !== null && $receipt->lastRemindedAt->getTimestamp() + ApprovalReminderEntity::COOLDOWN_SECONDS > $this->clock->now()->getTimestamp()) {
                return false;
            }
        } elseif ($source === ApprovalReminderEntity::OVERDUE) {
            if (!$receipt->remindOnOverdue || $receipt->overdueRemindedAt !== null || $receipt->dueAt === null || $receipt->dueAt > $this->clock->now()) {
                return false;
            }
        } else {
            return false;
        }

        $event = new ApprovalReminderPolicyEvent($task, $instance, $source, $context);
        $this->dispatcher->dispatch($event);

        return $event->isAllowed();
    }

    public function isCurrent(WorkflowTaskEntity $task, WorkflowInstanceEntity $instance): bool
    {
        return $instance->isRunning() && $task->isTodo()
            && $task->round === $instance->round && $task->nodeId === $instance->currentNodeId;
    }
}
