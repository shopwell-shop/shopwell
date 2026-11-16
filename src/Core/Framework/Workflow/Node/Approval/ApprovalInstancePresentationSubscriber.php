<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresentationEvent;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowTaskPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyEntity;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds human-task information when the published graph contains approval nodes.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalInstancePresentationSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<WorkflowCopyCollection> $copyRepository
     */
    public function __construct(
        private WorkflowTaskFinder $finder,
        private WorkflowTaskPresenter $taskPresenter,
        private WorkflowUserLookup $users,
        private EntityRepository $copyRepository,
        private ApprovalRoute $routes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowInstancePresentationEvent::class => 'present'];
    }

    public function present(WorkflowInstancePresentationEvent $event): void
    {
        if (!$event->withDetail) {
            return;
        }

        $instance = $event->instance;
        $graph = GraphData::fromArray($instance->snapshot());
        $approvalNodes = array_values(array_filter(
            $graph->nodesOfType(WorkflowNodeTypes::TASK),
            static fn (GraphNode $node): bool => $node->property('approvalStage') === true
        ));

        if ($approvalNodes === []) {
            return;
        }

        $context = $event->context;
        $plannedPath = $this->routes->path($graph, $instance->form());
        $currentTasks = $instance->currentNodeId === null ? [] : $this->finder
            ->forNode($instance->id, $instance->currentNodeId, $instance->round, $context)
            ->getElements();
        $approvalTasks = $this->taskPresenter->presentMany($this->finder->forInstance($instance->id, $context), $context);

        $event->addData([
            'plannedPath' => $plannedPath,
            'tasks' => $this->taskPresenter->presentMany($currentTasks, $context),
            'approvalTasks' => $approvalTasks,
            'approvalStages' => $this->presentStages($instance, $approvalNodes, $approvalTasks, $plannedPath['nodeIds'], $context),
            'history' => $this->presentHistory($instance->id, $context),
            'copies' => $this->presentCopies($instance->id, $context),
        ]);
    }

    /**
     * @param list<GraphNode> $approvalNodes
     * @param list<array<string, mixed>> $tasks
     * @param list<string> $plannedNodes
     *
     * @return list<array<string, mixed>>
     */
    private function presentStages(WorkflowInstanceEntity $instance, array $approvalNodes, array $tasks, array $plannedNodes, Context $context): array
    {
        $participantIds = [];

        foreach ($approvalNodes as $node) {
            $participantIds = [...$participantIds, ...$node->property('assigneeValue', [])];
        }

        $names = $this->users->names(array_values(array_unique($participantIds)), $context);
        $stages = [];

        foreach ($approvalNodes as $node) {
            $stageTasks = array_values(array_filter($tasks, static fn (array $task): bool => $task['nodeId'] === $node->id));
            $countersignType = $node->property('countersignType', WorkflowCountersignTypes::SINGLE);
            $stages[] = [
                'nodeId' => $node->id,
                'nodeName' => $node->property('name', $node->id),
                'description' => $node->property('description', ''),
                'countersignType' => $countersignType,
                'status' => $this->stageStatus($instance, $node->id, $stageTasks, $countersignType, $plannedNodes),
                'participants' => array_map(static fn (string $id): array => ['id' => $id, 'name' => $names[$id] ?? $id], $node->property('assigneeValue', [])),
                'tasks' => $stageTasks,
            ];
        }

        return $stages;
    }

    /**
     * @param list<array<string, mixed>> $tasks
     * @param list<string> $plannedNodes
     */
    private function stageStatus(WorkflowInstanceEntity $instance, string $nodeId, array $tasks, string $countersignType, array $plannedNodes): string
    {
        $states = array_column($tasks, 'state');

        if (\in_array(WorkflowTaskStates::REJECTED, $states, true)) {
            return 'rejected';
        }

        if ($instance->isRunning() && $instance->currentNodeId === $nodeId) {
            return 'active';
        }

        $hasAgreed = \in_array(WorkflowTaskStates::AGREED, $states, true);
        $hasPending = \in_array(WorkflowTaskStates::TODO, $states, true);
        $allAgreed = array_unique($states) === [WorkflowTaskStates::AGREED];

        if ($hasAgreed && !$hasPending && ($countersignType !== WorkflowCountersignTypes::COUNTERSIGN || $allAgreed)) {
            return 'approved';
        }

        if ($instance->isRunning() && \in_array($nodeId, $plannedNodes, true)) {
            return 'pending';
        }

        return 'skipped';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentHistory(string $instanceId, Context $context): array
    {
        $history = $this->finder->historyOf($instanceId, $context);

        $names = $this->users->names(
            array_map(static fn (WorkflowTaskHistoryEntity $task): string => $task->assigneeId, array_values($history->getElements())),
            $context
        );

        $rows = [];

        foreach ($history as $task) {
            $rows[] = [
                'id' => $task->id,
                'taskId' => $task->taskId,
                'nodeId' => $task->nodeId,
                'nodeName' => $task->nodeName,
                'assignee' => [
                    'id' => $task->assigneeId,
                    'name' => $names[$task->assigneeId] ?? $task->assigneeId,
                ],
                'countersignType' => $task->countersignType,
                'decision' => $task->decision,
                'comment' => $task->comment,
                'completedAt' => $task->completedAt?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentCopies(string $instanceId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));

        /** @var WorkflowCopyCollection $copies */
        $copies = $this->copyRepository->search($criteria, $context)->getEntities();

        $names = $this->users->names(
            array_map(static fn (WorkflowCopyEntity $copy): string => $copy->userId, array_values($copies->getElements())),
            $context
        );

        $rows = [];

        foreach ($copies as $copy) {
            $rows[] = [
                'userId' => $copy->userId,
                'name' => $names[$copy->userId] ?? $copy->userId,
                'readAt' => $copy->readAt?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }
}
