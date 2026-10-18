<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\Log\Package;

/**
 * The life cycle of one billing cycle of a subscription - one charge attempt per period.
 *
 * `unknown` exists because both mandate-driven providers accept a charge asynchronously: alipay
 * charges up to 24 hours after the acceptance and keeps trying for seven days, wechat keeps the
 * order processing and forbids a second attempt in the meantime. Writing `failed` would double
 * charge, writing `succeeded` would ship without money - only a query of the channel may move the
 * cycle out of `unknown`.
 */
#[Package('framework')]
final class PaymentSubscriptionCycleStates
{
    public const STATE_MACHINE = 'payment_subscription_cycle.state';

    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_SUBMITTED = 'submitted';
    public const STATE_PROCESSING = 'processing';
    public const STATE_SUCCEEDED = 'succeeded';
    public const STATE_FAILED_RETRYABLE = 'failed_retryable';
    public const STATE_FAILED_FINAL = 'failed_final';
    public const STATE_UNKNOWN = 'unknown';
    public const STATE_REFUNDED = 'refunded';
    public const STATE_PARTIAL_REFUNDED = 'partial_refunded';
    public const STATE_CLOSED = 'closed';

    public const ACTION_SUBMIT = 'submit';
    public const ACTION_PROCESS = 'process';
    public const ACTION_SUCCEED = 'succeed';

    /**
     * One name per destination. A single `fail` action pointing at both failure states would be
     * ambiguous: the registry resolves an action to exactly one destination and deprecates - and
     * from 6.8 throws on - an action that resolves to several.
     */
    public const ACTION_FAIL_RETRYABLE = 'fail_retryable';
    public const ACTION_FAIL_FINAL = 'fail_final';

    public const ACTION_MARK_UNKNOWN = 'mark_unknown';
    public const ACTION_RESCHEDULE = 'reschedule';
    public const ACTION_REFUND = 'refund';
    public const ACTION_REFUND_PARTIALLY = 'refund_partially';
    public const ACTION_CLOSE = 'close';
}
