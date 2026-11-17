<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderApiTest
 */
#[Package('framework')]
final readonly class ApprovalReminderPresenter
{
    public function __construct(private ApprovalReminderStore $store, private ApprovalReminderPolicy $policy, private ClockInterface $clock)
    {
    }

    /**
     * Read receipts only for tasks already authorized by the caller.
     *
     * @param list<WorkflowTaskEntity> $tasks
     *
     * @return array<string, array<string, mixed>>
     */
    public function presentMany(array $tasks, WorkflowInstanceCollection $instances, Context $context): array
    {
        $receipts = $this->store->forTasks(array_map(static fn (WorkflowTaskEntity $task): string => $task->id, $tasks), $context);
        $rows = [];
        foreach ($tasks as $task) {
            $receipt = $receipts->get($task->id);
            $instance = $instances->get($task->instanceId);
            if ($receipt === null || $instance === null) {
                continue;
            }
            $nextRemindAt = null;
            if ($receipt->lastRemindedAt !== null) {
                $nextRemindAt = \DateTimeImmutable::createFromInterface($receipt->lastRemindedAt)
                    ->modify('+' . ApprovalReminderEntity::COOLDOWN_SECONDS . ' seconds');
            }

            $rows[$task->id] = [
                'dueAt' => $receipt->dueAt?->format(\DateTimeInterface::ATOM),
                'overdue' => $this->policy->isCurrent($task, $instance) && $receipt->dueAt !== null && $receipt->dueAt <= $this->clock->now(),
                'lastRemindedAt' => $receipt->lastRemindedAt?->format(\DateTimeInterface::ATOM),
                'source' => $receipt->source,
                'deliveryPending' => $receipt->deliveryId !== null && $receipt->deliveredAt === null,
                'nextRemindAt' => $nextRemindAt?->format(\DateTimeInterface::ATOM),
                'count' => $receipt->reminderCount,
                'canRemind' => $this->policy->allows($task, $instance, $receipt, ApprovalReminderEntity::MANUAL, $context),
            ];
        }

        return $rows;
    }
}
