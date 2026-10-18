<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\Log\Package;

/**
 * The unified life cycle of a subscription instance, shared by every provider.
 *
 * The provider states are mapped onto it - Stripe and PayPal report them, the mandate-driven
 * providers (alipay, wechat) are derived from the mandate and the cycles. `canceled` and `expired`
 * are final and have no outgoing transition.
 */
#[Package('framework')]
final class PaymentSubscriptionStates
{
    public const STATE_MACHINE = 'payment_subscription.status';

    public const STATE_PENDING = 'pending';
    public const STATE_TRIALING = 'trialing';
    public const STATE_ACTIVE = 'active';
    public const STATE_PAST_DUE = 'past_due';
    public const STATE_SUSPENDED = 'suspended';
    public const STATE_CANCELED = 'canceled';
    public const STATE_EXPIRED = 'expired';

    public const ACTION_TRIAL = 'trial';
    public const ACTION_ACTIVATE = 'activate';
    public const ACTION_FAIL = 'fail';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_EXPIRE = 'expire';
}
