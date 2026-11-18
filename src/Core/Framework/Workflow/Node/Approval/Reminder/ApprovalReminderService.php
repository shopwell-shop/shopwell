<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Persist the private receipt and audit under the instance lock, then deliver outside the transaction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderServiceTest
 */
#[Package('framework')]
final readonly class ApprovalReminderService
{
    /**
     * @param EntityRepository<ApprovalReminderCollection> $reminders
     */
    public function __construct(
        private EntityRepository $reminders,
        private ApprovalReminderStore $store,
        private WorkflowService $workflows,
        private WorkflowTaskFinder $tasks,
        private WorkflowTransactionRunner $transactions,
        private WorkflowRecorder $recorder,
        private ClockInterface $clock,
        private ApprovalReminderPolicy $policy,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
        private Connection $connection,
    ) {
    }

    public function remind(string $instanceId, Context $context): void
    {
        $this->requireIndependentTransaction();
        $actorId = $this->workflows->actorId($context);
        $ids = $this->transactions->run(function () use ($instanceId, $actorId, $context): array {
            $this->transactions->lockInstance($instanceId);
            $instance = $this->workflows->loadInstance($instanceId, $context);
            if ($instance->initiatorId !== $actorId || !$context->isAllowed('workflow_instance:start')) {
                throw WorkflowExecutionException::forbidden('Only the initiator may remind pending approvers.');
            }

            $tasks = $this->tasks->forInstance($instanceId, $context);
            $receipts = $this->store->forTasks(array_values($tasks->getIds()), $context);
            $ids = [];
            foreach ($tasks as $task) {
                $receipt = $receipts->get($task->id);
                if ($receipt === null || !$this->policy->allows($task, $instance, $receipt, ApprovalReminderEntity::MANUAL, $context)) {
                    continue;
                }
                $this->record($receipt, $task, ApprovalReminderEntity::MANUAL, $actorId, $context);
                $ids[] = $receipt->id;
            }
            if ($ids === []) {
                throw WorkflowExecutionException::reminderUnavailable();
            }

            return $ids;
        });

        foreach ($ids as $id) {
            $this->deliver($id);
        }
    }

    public function runDue(): void
    {
        $this->requireIndependentTransaction();
        $context = Context::createCLIContext();
        $pending = (new Criteria())->addFilter(
            new EqualsFilter('deliveredAt', null),
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('deliveryId', null)]),
        );
        $this->eachReceipt($pending, $context, fn (ApprovalReminderEntity $receipt) => $this->deliver($receipt->id));

        $due = (new Criteria())->addFilter(
            new EqualsFilter('remindOnOverdue', true),
            new EqualsFilter('overdueRemindedAt', null),
            new RangeFilter('dueAt', [RangeFilter::LTE => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)]),
            new EqualsFilter('task.stateMachineState.technicalName', WorkflowTaskStates::TODO),
            new EqualsFilter('instance.stateMachineState.technicalName', WorkflowInstanceStates::RUNNING),
        );
        $this->eachReceipt($due, $context, function (ApprovalReminderEntity $receipt) use ($context): void {
            $queued = $this->transactions->run(function () use ($receipt, $context): bool {
                $this->transactions->lockInstance($receipt->instanceId);
                $fresh = $this->store->get($receipt->id, $context);
                $instance = $this->workflows->loadInstance($receipt->instanceId, $context);
                $task = $this->tasks->forInstance($instance->id, $context)->get($receipt->taskId);
                if ($fresh === null || $task === null || !$this->policy->allows($task, $instance, $fresh, ApprovalReminderEntity::OVERDUE, $context)) {
                    return false;
                }
                $this->record($fresh, $task, ApprovalReminderEntity::OVERDUE, null, $context);

                return true;
            });
            if ($queued) {
                $this->deliver($receipt->id);
            }
        });
    }

    private function requireIndependentTransaction(): void
    {
        // A pre-existing REPEATABLE READ snapshot can remain stale after a row lock.
        // Start a fresh transaction so admission observes the latest committed state.
        if ($this->connection->isTransactionActive()) {
            throw WorkflowExecutionException::reminderRequiresCommit();
        }
    }

    private function record(ApprovalReminderEntity $receipt, WorkflowTaskEntity $task, string $source, ?string $actorId, Context $context): void
    {
        $deliveryId = Uuid::randomHex();
        $now = $this->clock->now();
        $payload = [
            'id' => $receipt->id,
            'lastRemindedAt' => $now,
            'reminderCount' => $receipt->reminderCount + 1,
            'source' => $source,
            'deliveryId' => $deliveryId,
            'deliveredAt' => null,
        ];
        if ($source === ApprovalReminderEntity::OVERDUE) {
            $payload['overdueRemindedAt'] = $now;
        }
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($payload, $receipt, $task, $source, $actorId, $deliveryId): void {
            $this->reminders->update([$payload], $scoped);
            $this->recorder->record($task->instanceId, 'remind', $task->id, $actorId, null, [
                'deliveryId' => $deliveryId, 'source' => $source,
                'assigneeId' => $task->assigneeId, 'round' => $task->round,
                'dueAt' => $receipt->dueAt?->format(\DateTimeInterface::ATOM),
            ], $scoped);
        });
    }

    private function deliver(string $id): void
    {
        $context = Context::createCLIContext();
        $receipt = $this->store->get($id, $context);
        if ($receipt === null || $receipt->deliveryId === null || $receipt->deliveredAt !== null) {
            return;
        }
        $instance = $this->workflows->loadInstance($receipt->instanceId, $context);
        $task = $this->tasks->forInstance($instance->id, $context)->get($receipt->taskId);
        try {
            if ($task !== null && $this->policy->isCurrent($task, $instance)) {
                $node = GraphData::fromArray($instance->snapshot())->node($task->nodeId);
                $deadline = \array_key_exists('approvalDeadline', $node->properties)
                    ? ApprovalDeadline::fromConfiguration($node->properties['approvalDeadline'])
                    : null;
                $this->dispatcher->dispatch(new ApprovalReminderDeliveryEvent(
                    $receipt->deliveryId,
                    $task->id,
                    $instance->id,
                    $task->assigneeId,
                    $receipt->source ?? throw WorkflowExecutionException::invalidGraph('A pending reminder requires its source.'),
                    $receipt->lastRemindedAt ?? throw WorkflowExecutionException::invalidGraph('A pending reminder requires its request time.'),
                    $receipt->dueAt,
                    $deadline?->escalateTo,
                ));
            }
            $this->transactions->run(function () use ($receipt, $context): void {
                $this->transactions->lockInstance($receipt->instanceId);
                $fresh = $this->store->get($receipt->id, $context);
                if ($fresh !== null && $fresh->deliveryId === $receipt->deliveryId && $fresh->deliveredAt === null) {
                    $this->reminders->update([['id' => $receipt->id, 'deliveredAt' => $this->clock->now()]], $context);
                }
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Approval reminder delivery failed; the scheduled task will retry.', [
                'deliveryId' => $receipt->deliveryId, 'exception' => $exception,
            ]);
        }
    }

    /**
     * @param \Closure(ApprovalReminderEntity): void $operation
     */
    private function eachReceipt(Criteria $criteria, Context $context, \Closure $operation): void
    {
        $criteria->setLimit(100)->addSorting(new FieldSorting('id'));
        $lastId = null;
        do {
            $page = clone $criteria;
            if ($lastId !== null) {
                $page->addFilter(new RangeFilter('id', [RangeFilter::GT => Uuid::fromHexToBytes($lastId)]));
            }
            $receipts = $this->reminders->search($page, $context)->getEntities();
            foreach ($receipts as $receipt) {
                $operation($receipt);
                $lastId = $receipt->id;
            }
        } while ($receipts->count() === 100);
    }
}
