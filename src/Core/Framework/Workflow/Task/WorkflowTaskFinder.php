<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogCollection;

/**
 * Shared Criteria queries for tasks, history and logs; centralize node and round filters used by aggregation and presentation.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowTaskFinder
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $taskRepository
     * @param EntityRepository<WorkflowTaskHistoryCollection> $hisTaskRepository
     * @param EntityRepository<WorkflowLogCollection> $logRepository
     */
    public function __construct(
        private readonly EntityRepository $taskRepository,
        private readonly EntityRepository $hisTaskRepository,
        private readonly EntityRepository $logRepository,
    ) {
    }

    /**
     * Return tasks for exactly one instance, node and round so aggregation cannot mix rounds.
     */
    public function forNode(string $instanceId, string $nodeId, int $round, Context $context): WorkflowTaskCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addFilter(new EqualsFilter('nodeId', $nodeId));
        $criteria->addFilter(new EqualsFilter('round', $round));
        $criteria->addSorting(new FieldSorting('sequence', FieldSorting::ASCENDING));

        $criteria->addAssociation('stateMachineState');

        /** @var WorkflowTaskCollection $tasks */
        $tasks = $this->taskRepository->search($criteria, $context)->getEntities();

        return $tasks;
    }

    public function forInstance(string $instanceId, Context $context): WorkflowTaskCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('instanceId', $instanceId))
            ->addSorting(new FieldSorting('round'), new FieldSorting('sequence'), new FieldSorting('id'))
            ->addAssociation('stateMachineState');

        return $this->taskRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Apply mandatory current-user and todo filters to presenter-supplied pagination and search criteria.
     *
     * @return EntitySearchResult<WorkflowTaskCollection>
     */
    public function todoOf(string $userId, Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addFilter(new EqualsFilter('assigneeId', $userId));
        $criteria->addFilter(new EqualsFilter('stateMachineState.technicalName', WorkflowTaskStates::TODO));

        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        }

        $criteria->addAssociation('stateMachineState');

        return $this->taskRepository->search($criteria, $context);
    }

    /**
     * Read completed decisions from immutable history rather than mutable tasks.
     *
     * @return EntitySearchResult<WorkflowTaskHistoryCollection>
     */
    public function doneOf(string $userId, Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addFilter(new EqualsFilter('assigneeId', $userId));

        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('completedAt', FieldSorting::DESCENDING));
        }

        return $this->hisTaskRepository->search($criteria, $context);
    }

    public function historyOf(string $instanceId, Context $context): WorkflowTaskHistoryCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addSorting(new FieldSorting('completedAt', FieldSorting::ASCENDING));

        /** @var WorkflowTaskHistoryCollection $hisTasks */
        $hisTasks = $this->hisTaskRepository->search($criteria, $context)->getEntities();

        return $hisTasks;
    }

    public function logsOf(string $instanceId, Context $context): WorkflowLogCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        /** @var WorkflowLogCollection $logs */
        $logs = $this->logRepository->search($criteria, $context)->getEntities();

        return $logs;
    }

    /**
     * Return the pending count without hydrating entities.
     */
    public function todoCount(string $userId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('assigneeId', $userId));
        $criteria->addFilter(new EqualsFilter('stateMachineState.technicalName', WorkflowTaskStates::TODO));
        $criteria->setLimit(1);

        return $this->taskRepository->search($criteria, $context)->getTotal();
    }
}
