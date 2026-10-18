<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * The only way a subscription cycle changes its state.
 *
 * Every transition of the cycle state machine funnels through this handler, so the action names of
 * the machine are named exactly once and the services never assemble a {@see Transition}
 * themselves. Scheduling runs without a session of its own, which is why every transition runs on
 * the default context.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentCycleStateHandler
{
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function submit(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_SUBMIT);
    }

    public function process(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_PROCESS);
    }

    public function succeed(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_SUCCEED);
    }

    /**
     * The retryable failure: the money problem is temporary, the cycle stays in the queue.
     */
    public function failRetryable(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE);
    }

    /**
     * The final failure: the mandate or the trade is dead, retrying would only burn API calls.
     *
     * The two failure states carry two action names on purpose - one action resolving to two
     * destinations is ambiguous, and the registry refuses that from 6.8 on.
     */
    public function failFinal(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL);
    }

    public function markUnknown(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_MARK_UNKNOWN);
    }

    public function reschedule(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_RESCHEDULE);
    }

    public function refund(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_REFUND);
    }

    public function refundPartially(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_REFUND_PARTIALLY);
    }

    public function close(string $cycleId): void
    {
        $this->transition($cycleId, PaymentSubscriptionCycleStates::ACTION_CLOSE);
    }

    private function transition(string $cycleId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(PaymentSubscriptionCycleEntity::ENTITY_NAME, $cycleId, $action, 'stateId'),
            Context::createDefaultContext()
        );
    }
}
