<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * The only way a return changes its state.
 *
 * Every transition funnels through this handler, so the action names of the machine are named exactly
 * once and no service assembles a {@see Transition} on its own. The context is passed in and not
 * created here: a decision of an administrator has to end up in the state history with that
 * administrator, which is what a dispute is answered with.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnStateHandler
{
    /**
     * @internal
     */
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function approve(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_APPROVE, $context);
    }

    public function decline(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_DECLINE, $context);
    }

    public function cancel(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_CANCEL, $context);
    }

    public function ship(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_SHIP, $context);
    }

    public function receive(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_RECEIVE, $context);
    }

    public function refund(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_REFUND, $context);
    }

    public function finish(string $returnId, Context $context): void
    {
        $this->transition($returnId, OrderReturnStates::ACTION_FINISH, $context);
    }

    /**
     * The state a return is in right now, as the technical name of the state machine.
     */
    public function currentState(OrderReturnEntity $orderReturn): ?string
    {
        return $orderReturn->stateMachineState?->getTechnicalName();
    }

    private function transition(string $returnId, string $action, Context $context): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(OrderReturnEntity::ENTITY_NAME, $returnId, $action, 'stateId'),
            $context
        );
    }
}
