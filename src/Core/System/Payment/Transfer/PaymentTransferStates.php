<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Transfer;

use Shopwell\Core\Framework\Log\Package;

/**
 * The life cycle of one payout of the platform to one recipient.
 *
 * `succeeded` is deliberately not final: stripe reports a payout as `paid` and changes it to `failed`
 * within five business days, alipay refunds a transfer to a bank card after the bank refused it and
 * paypal turns a claimed payout into `returned`. Every one of those edges is a transition below -
 * without them the first such notification would be an illegal transition and the money would be
 * tracked in the wrong state.
 *
 * `awaiting_user_confirm` only exists for wechat: it hands a `package_info` to the shopper, who has
 * to accept the transfer within 24 hours, and the platform can not do anything but wait until then.
 *
 * Every action resolves to exactly one destination per origin state - see the migration test that
 * asserts it for the whole machine.
 */
#[Package('framework')]
final class PaymentTransferStates
{
    public const STATE_MACHINE = 'payment_transfer.status';

    public const STATE_CREATED = 'created';
    public const STATE_SUBMITTED = 'submitted';
    public const STATE_AWAITING_USER_CONFIRM = 'awaiting_user_confirm';
    public const STATE_PROCESSING = 'processing';
    public const STATE_SUCCEEDED = 'succeeded';
    public const STATE_FAILED = 'failed';
    public const STATE_CANCELED = 'canceled';
    public const STATE_RETURNED = 'returned';

    public const ACTION_SUBMIT = 'submit';
    public const ACTION_PROCESS = 'process';
    public const ACTION_AWAIT_CONFIRM = 'await_confirm';
    public const ACTION_SUCCEED = 'succeed';
    public const ACTION_FAIL = 'fail';
    public const ACTION_CANCEL = 'cancel';

    /**
     * The payout reached the recipient and came back afterwards: alipay refunds a transfer to a bank
     * card when the bank rejects the account, paypal returns a payout nobody claimed within 30 days.
     */
    public const ACTION_MARK_RETURNED = 'mark_returned';

    /**
     * How far a recipient is onboarded on the channel side. This is not a state machine on purpose:
     * the status is driven by the channel (stripe's `account.updated` webhook) and the platform only
     * mirrors it - a local machine would add a second source of truth that can disagree with the
     * channel.
     */
    public const ONBOARDING_UNREGISTERED = 'unregistered';
    public const ONBOARDING_PENDING = 'pending';
    public const ONBOARDING_REGISTERED = 'registered';
    public const ONBOARDING_RESTRICTED = 'restricted';
}
