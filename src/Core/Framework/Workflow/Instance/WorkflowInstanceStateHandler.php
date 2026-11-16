<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Instance;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * Transition instances and write endedAt together through the state machine to preserve history and terminal-state constraints.
 *
 * @internal
 */
#[Package('framework')]
final class WorkflowInstanceStateHandler
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instanceRepository
     */
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly EntityRepository $instanceRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function initialStateId(Context $context): string
    {
        return $this->stateMachineRegistry->getStateMachine(WorkflowInstanceStates::STATE_MACHINE, $context)->getInitialStateId()
            ?? throw WorkflowExecutionException::invalidGraph('The workflow instance state machine has no initial state.');
    }

    public function complete(string $instanceId, string $result, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_COMPLETE, $context);
        $this->instanceRepository->update([['id' => $instanceId, 'result' => $result]], $context);
    }

    public function agree(string $instanceId, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_AGREE, $context);
    }

    public function reject(string $instanceId, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_REJECT, $context);
    }

    public function cancel(string $instanceId, Context $context): void
    {
        $this->transition($instanceId, WorkflowInstanceStates::ACTION_CANCEL, $context);
    }

    /**
     * Transitions are allowed only from running; do not swallow StateMachineException for duplicate decisions.
     */
    private function transition(string $instanceId, string $action, Context $context): void
    {
        // The state machine selects system scope internally.
        $this->stateMachineRegistry->transition(
            new Transition(WorkflowInstanceEntity::ENTITY_NAME, $instanceId, $action, 'stateId'),
            $context
        );

        $this->instanceRepository->update([
            [
                'id' => $instanceId,
                'endedAt' => $this->clock->now(),
            ],
        ], $context);
    }
}
