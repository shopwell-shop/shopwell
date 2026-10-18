<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * The only way a subscription instance changes its status.
 *
 * Every transition of the subscription state machine funnels through this handler, so the action
 * names of the machine are named exactly once and the services never assemble a {@see Transition}
 * themselves. Webhooks and scheduled tasks run without a session of their own, which is why every
 * transition runs on the default context.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentSubscriptionStateHandler
{
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function trial(string $subscriptionId): void
    {
        $this->transition($subscriptionId, PaymentSubscriptionStates::ACTION_TRIAL);
    }

    public function activate(string $subscriptionId): void
    {
        $this->transition($subscriptionId, PaymentSubscriptionStates::ACTION_ACTIVATE);
    }

    /**
     * The dunning state: the mandate is still valid, a period just could not be collected.
     */
    public function fail(string $subscriptionId): void
    {
        $this->transition($subscriptionId, PaymentSubscriptionStates::ACTION_FAIL);
    }

    public function suspend(string $subscriptionId): void
    {
        $this->transition($subscriptionId, PaymentSubscriptionStates::ACTION_SUSPEND);
    }

    public function cancel(string $subscriptionId): void
    {
        $this->transition($subscriptionId, PaymentSubscriptionStates::ACTION_CANCEL);
    }

    public function expire(string $subscriptionId): void
    {
        $this->transition($subscriptionId, PaymentSubscriptionStates::ACTION_EXPIRE);
    }

    private function transition(string $subscriptionId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(PaymentSubscriptionEntity::ENTITY_NAME, $subscriptionId, $action, 'statusId'),
            Context::createDefaultContext()
        );
    }
}
