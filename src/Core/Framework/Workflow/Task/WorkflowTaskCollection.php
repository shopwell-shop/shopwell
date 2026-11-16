<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;

/**
 * Task collections provide counts; CounterSignCalculator applies approval decision rules.
 *
 * @internal
 *
 * @extends EntityCollection<WorkflowTaskEntity>
 */
#[Package('framework')]
class WorkflowTaskCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_task_collection';
    }

    /**
     * Keep tasks in counted states, excluding canceled or skipped tasks from approval aggregation.
     *
     * @param list<string> $states
     */
    public function filterByStates(array $states): static
    {
        return $this->filter(
            static fn (WorkflowTaskEntity $task): bool => \in_array($task->state(), $states, true)
        );
    }

    public function countByState(string $state): int
    {
        $count = 0;

        foreach ($this->getIterator() as $task) {
            if ($task->state() === $state) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Check the task with the highest sequence; sequential tasks are created incrementally, so counting current approvals is insufficient.
     */
    public function lastSequenceIsAgreed(): bool
    {
        $last = null;

        foreach ($this->getIterator() as $task) {
            if ($last === null || $task->sequence > $last->sequence) {
                $last = $task;
            }
        }

        return $last !== null && $last->state() === WorkflowTaskStates::AGREED;
    }

    /**
     * Return the pending task with the lowest sequence as a deterministic fallback for inconsistent data.
     */
    public function nextTodo(): ?WorkflowTaskEntity
    {
        $next = null;

        foreach ($this->getIterator() as $task) {
            if (!$task->isTodo()) {
                continue;
            }

            if ($next === null || $task->sequence < $next->sequence) {
                $next = $task;
            }
        }

        return $next;
    }

    protected function getExpectedClass(): string
    {
        return WorkflowTaskEntity::class;
    }
}
