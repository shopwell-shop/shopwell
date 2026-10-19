<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Refund;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * The only way a payment refund changes its state.
 *
 * Every transition of the refund state machine funnels through this handler, so the action names of
 * the machine are named exactly once and the refund service never assembles a {@see Transition}
 * itself. The payment api has no session context of its own, which is why every transition runs on
 * the default context.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentRefundStateHandler
{
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function process(string $refundId): void
    {
        $this->transition($refundId, PaymentRefundStates::ACTION_PROCESS);
    }

    public function finish(string $refundId): void
    {
        $this->transition($refundId, PaymentRefundStates::ACTION_FINISH);
    }

    public function fail(string $refundId): void
    {
        $this->transition($refundId, PaymentRefundStates::ACTION_FAIL);
    }

    private function transition(string $refundId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(PaymentRefundEntity::ENTITY_NAME, $refundId, $action, 'stateId'),
            Context::createDefaultContext()
        );
    }
}
