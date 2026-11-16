<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * Transition tasks and write completedAt together; preserve illegal-transition failures for duplicate decisions.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowTaskStateHandler
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $taskRepository
     */
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly EntityRepository $taskRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function initialStateId(Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine(WorkflowTaskStates::STATE_MACHINE, $context)->getInitialStateId()
            ?? throw WorkflowExecutionException::invalidGraph('The approval state machine has no initial state.');
    }

    public function agree(string $taskId, Context $context): void
    {
        $this->transition($taskId, WorkflowTaskStates::ACTION_AGREE, $context);
    }

    public function reject(string $taskId, Context $context): void
    {
        $this->transition($taskId, WorkflowTaskStates::ACTION_REJECT, $context);
    }

    /**
     * Cancel a task and exclude it from approval aggregation through WorkflowTaskStates::COUNTED_STATES.
     */
    public function cancel(string $taskId, Context $context): void
    {
        $this->transition($taskId, WorkflowTaskStates::ACTION_CANCEL, $context);
    }

    private function transition(string $taskId, string $action, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(WorkflowTaskEntity::ENTITY_NAME, $taskId, $action, 'stateId'),
            $context
        );

        $this->taskRepository->update([
            [
                'id' => $taskId,
                'completedAt' => $this->clock->now(),
            ],
        ], $context);
    }
}
