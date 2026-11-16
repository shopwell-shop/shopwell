<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;

/**
 * Shared task representation for lists, instance details and decision panels.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowTaskPresenter
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instanceRepository
     */
    public function __construct(
        private readonly WorkflowUserLookup $users,
        private readonly EntityRepository $instanceRepository,
    ) {
    }

    /**
     * Present a task list with one batched user-name lookup.
     *
     * @param iterable<WorkflowTaskEntity> $tasks
     *
     * @return list<array<string, mixed>>
     */
    public function presentMany(iterable $tasks, Context $context, bool $withInstanceSummary = false): array
    {
        $taskList = [];

        foreach ($tasks as $task) {
            $taskList[] = $task;
        }

        $names = $this->users->names(
            array_map(static fn (WorkflowTaskEntity $task): string => $task->assigneeId, $taskList),
            $context
        );

        $summaries = $withInstanceSummary
            ? $this->instanceSummaries(array_map(static fn (WorkflowTaskEntity $task): string => $task->instanceId, $taskList), $context)
            : [];

        return array_map(
            fn (WorkflowTaskEntity $task): array => array_merge($this->present($task, $context, $names), $summaries[$task->instanceId] ?? []),
            $taskList
        );
    }

    /**
     * Resolve summaries only for instances from the caller's authorized task result.
     *
     * @param list<string> $instanceIds
     *
     * @return array<string, array{title: string, number: string, initiator: array{id: string, name: string}}>
     */
    public function instanceSummaries(array $instanceIds, Context $context): array
    {
        if ($instanceIds === []) {
            return [];
        }

        $instances = $this->instanceRepository->search(new Criteria(array_values(array_unique($instanceIds))), $context)->getEntities();
        $names = $this->users->names(
            array_map(static fn (WorkflowInstanceEntity $instance): string => $instance->initiatorId, array_values($instances->getElements())),
            $context
        );
        $summaries = [];
        foreach ($instances as $instance) {
            $summaries[$instance->id] = [
                'title' => $instance->title,
                'number' => $instance->number,
                'initiator' => ['id' => $instance->initiatorId, 'name' => $names[$instance->initiatorId] ?? $instance->initiatorId],
            ];
        }

        return $summaries;
    }

    /**
     * @param array<string, string>|null $names
     *
     * @return array<string, mixed>
     */
    public function present(WorkflowTaskEntity $task, Context $context, ?array $names = null): array
    {
        $names ??= $this->users->names([$task->assigneeId], $context);

        return [
            'id' => $task->id,
            'instanceId' => $task->instanceId,
            'nodeId' => $task->nodeId,
            'nodeName' => $task->nodeName,
            'countersignType' => $task->countersignType,
            'assigneeType' => $task->assigneeType,
            'assignee' => [
                'id' => $task->assigneeId,
                'name' => $names[$task->assigneeId] ?? $task->assigneeId,
            ],
            'state' => $task->state(),
            // Whether the frontend may display decision actions.
            'actionable' => $task->state() === WorkflowTaskStates::TODO,
            'round' => $task->round,
            'sequence' => $task->sequence,
            'createdAt' => $task->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $task->completedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
