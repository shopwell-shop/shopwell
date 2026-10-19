<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * The only way a payment order changes its state.
 *
 * Every transition of the payment order state machine funnels through this handler, so the action
 * names of the machine are named exactly once and the services never assemble a {@see Transition}
 * themselves. The payment api has no session context of its own, which is why every transition runs
 * on the default context.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentOrderStateHandler
{
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function open(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_PROCESS);
    }

    public function pay(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_PAY);
    }

    public function fail(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_FAIL);
    }

    public function close(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_CLOSE);
    }

    public function expire(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_EXPIRE);
    }

    public function refund(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_REFUND);
    }

    public function refundPartially(string $orderId): void
    {
        $this->transition($orderId, PaymentOrderStates::ACTION_REFUND_PARTIALLY);
    }

    private function transition(string $orderId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(PaymentOrderEntity::ENTITY_NAME, $orderId, $action, 'stateId'),
            Context::createDefaultContext()
        );
    }
}
