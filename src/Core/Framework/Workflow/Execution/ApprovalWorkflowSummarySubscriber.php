<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatus;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatusesEvent;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal Read projection; execution and financial decisions always revalidate authoritative state.
 */
#[Package('framework')]
final readonly class ApprovalWorkflowSummarySubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instances
     * @param EntityRepository<WorkflowTaskCollection> $tasks
     * @param EntityRepository<WorkflowCopyCollection> $copies
     */
    public function __construct(
        private EntityRepository $instances,
        private EntityRepository $tasks,
        private EntityRepository $copies,
        private WorkflowUserLookup $users,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowRuntimeStatusesEvent::class => 'load'];
    }

    public function load(WorkflowRuntimeStatusesEvent $event): void
    {
        $references = array_filter($event->references, static fn (array $reference): bool => $reference['publisher'] === ApprovalWorkflowPublisher::NAME);
        if ($references === []) {
            return;
        }
        $instances = $this->instances->search((new Criteria(array_keys($references)))->addAssociation('stateMachineState'), $event->context)->getEntities();
        $instances = $instances->filter(static fn ($instance): bool => $instance->definitionId === $references[$instance->id]['releaseId']);
        if ($instances->count() === 0) {
            return;
        }
        $ids = $instances->getIds();
        $tasks = $this->tasks->search((new Criteria())
            ->addFilter(new EqualsAnyFilter('instanceId', $ids))
            ->addAssociation('stateMachineState')
            ->addSorting(new FieldSorting('sequence'), new FieldSorting('id')), $event->context)->getEntities();
        $source = $event->context->getSource();
        $actor = $source instanceof AdminApiSource ? $source->getUserId() : null;
        $participants = [];
        if ($actor !== null && $event->context->isAllowed('workflow_instance:access')) {
            foreach ($tasks as $task) {
                if ($task->assigneeId === $actor) {
                    $participants[$task->instanceId] = true;
                }
            }
            $copies = $this->copies->search((new Criteria())->addFilter(new EqualsAnyFilter('instanceId', $ids), new EqualsFilter('userId', $actor)), $event->context)->getEntities();
            foreach ($copies as $copy) {
                $participants[$copy->instanceId] = true;
            }
        }
        $current = [];
        foreach ($tasks as $task) {
            $instance = $instances->get($task->instanceId);
            if ($instance !== null && $instance->state() === WorkflowInstanceStates::RUNNING && $task->round === $instance->round && $task->isTodo()) {
                $current[$task->instanceId][$task->assigneeId] = $task->assigneeId;
            }
        }
        $names = $this->users->names(array_values(array_unique(array_merge([], ...array_values($current)))), $event->context);
        foreach ($instances as $instance) {
            $status = match ($instance->state()) {
                WorkflowInstanceStates::RUNNING => WorkflowRuntimeStatus::PENDING,
                WorkflowInstanceStates::AGREED => WorkflowRuntimeStatus::APPROVED,
                WorkflowInstanceStates::REJECTED => WorkflowRuntimeStatus::REJECTED,
                WorkflowInstanceStates::CANCELED => WorkflowRuntimeStatus::CANCELED,
                default => null,
            };
            if ($status === null) {
                continue;
            }
            $detailRoute = null;
            if ($actor !== null && $event->context->isAllowed('workflow_instance:access') && ($actor === $instance->initiatorId || isset($participants[$instance->id]))) {
                $detailRoute = ['name' => 'sw.workflow.instance', 'params' => ['id' => $instance->id]];
            }
            $assignees = [];
            foreach ($current[$instance->id] ?? [] as $id) {
                $assignees[] = ['id' => $id, 'name' => $names[$id] ?? $id];
            }
            $event->setStatus($instance->id, new WorkflowRuntimeStatus($status, $detailRoute), $assignees);
        }
    }
}
