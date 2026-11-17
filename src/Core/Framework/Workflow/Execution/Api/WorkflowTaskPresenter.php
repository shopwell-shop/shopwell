<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActions;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderPresenter;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;

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
        private readonly ApprovalTaskPolicy $operations,
        private readonly ApprovalReminderPresenter $reminders,
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

        $instances = $this->instances(array_map(static fn (WorkflowTaskEntity $task): string => $task->instanceId, $taskList), $context);
        $summaries = $withInstanceSummary ? $this->summaries($instances, $context) : [];

        $reminders = $this->reminders->presentMany($taskList, $instances, $context);

        return array_map(
            fn (WorkflowTaskEntity $task): array => array_merge(
                $this->present($task, $context, $names, $instances->get($task->instanceId)),
                $summaries[$task->instanceId] ?? [],
                ['reminder' => $reminders[$task->id] ?? null],
            ),
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
        return $this->summaries($this->instances($instanceIds, $context), $context);
    }

    /**
     * @param array<string, string> $names
     *
     * @return array<string, mixed>
     */
    private function present(WorkflowTaskEntity $task, Context $context, array $names, ?WorkflowInstanceEntity $instance): array
    {
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
            'actionable' => $task->isTodo(),
            'availableActions' => $instance !== null ? $this->operations->available($task, $instance, $context) : [],
            'requiredReviewer' => ($task->ext[ApprovalTaskActions::EXT_PARTICIPATION] ?? null) === ApprovalTaskActions::REQUIRED,
            'round' => $task->round,
            'sequence' => $task->sequence,
            'createdAt' => $task->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'completedAt' => $task->completedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<string> $instanceIds
     */
    private function instances(array $instanceIds, Context $context): WorkflowInstanceCollection
    {
        if ($instanceIds === []) {
            return new WorkflowInstanceCollection();
        }

        $criteria = (new Criteria(array_values(array_unique($instanceIds))))->addAssociation('stateMachineState');

        return $this->instanceRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @return array<string, array{title: string, number: string, initiator: array{id: string, name: string}}>
     */
    private function summaries(WorkflowInstanceCollection $instances, Context $context): array
    {
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
}
