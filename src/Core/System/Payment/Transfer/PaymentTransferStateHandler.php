<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;

/**
 * The only way a transfer changes its state.
 *
 * Every transition of the transfer state machine funnels through this handler, so the action names of
 * the machine are named exactly once and the services never assemble a {@see Transition} themselves.
 * Payouts are driven by webhooks and by a scheduler as well, which is why every transition runs on the
 * default context.
 *
 * The handler deliberately takes no business decision: `fail` is the same action whether the failure
 * arrives from `submitted`, `processing` or - the stripe case - from `succeeded`. Which origin state a
 * notification means is decided by the service that reads the channel answer.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentTransferStateHandler
{
    public function __construct(private readonly StateMachineRegistry $stateMachineRegistry)
    {
    }

    public function submit(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_SUBMIT);
    }

    public function process(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_PROCESS);
    }

    /**
     * Wechat only: the shopper has to accept the transfer in their app before the money moves.
     */
    public function awaitConfirm(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_AWAIT_CONFIRM);
    }

    public function succeed(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_SUCCEED);
    }

    /**
     * Also the way back out of `succeeded`: stripe reports a payout as `paid` and may turn it into
     * `failed` within five business days, and neither alipay's refund of a card transfer nor paypal's
     * failure is expressed with an action of its own.
     */
    public function fail(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_FAIL);
    }

    public function cancel(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_CANCEL);
    }

    /**
     * The money reached the recipient and came back: alipay's `REFUND` of a card transfer, paypal's
     * `RETURNED` of a payout nobody claimed.
     */
    public function markReturned(string $transferId): void
    {
        $this->transition($transferId, PaymentTransferStates::ACTION_MARK_RETURNED);
    }

    private function transition(string $transferId, string $action): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(PaymentTransferEntity::ENTITY_NAME, $transferId, $action, 'statusId'),
            Context::createDefaultContext()
        );
    }
}
