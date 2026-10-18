<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription;

use Shopwell\Core\Framework\Log\Package;

/**
 * The life cycle of a signing agreement (mandate) of the mandate-driven providers.
 *
 * `pending` expires when the signing session runs out (wechat gives the pre-sign session ten
 * minutes), `active` expires when the agreement runs out of its total times. `pending` may also be
 * terminated - the channel rejecting the signing is not the same as the session running out, and
 * the two must stay distinguishable to decide whether a retry makes sense at all. A suspended
 * agreement resumes when the customer repairs whatever suspended it.
 */
#[Package('framework')]
final class PaymentMandateStates
{
    public const STATE_MACHINE = 'payment_mandate.status';

    public const STATE_INIT = 'init';
    public const STATE_PENDING = 'pending';
    public const STATE_ACTIVE = 'active';
    public const STATE_SUSPENDED = 'suspended';
    public const STATE_TERMINATED = 'terminated';
    public const STATE_EXPIRED = 'expired';

    public const ACTION_SUBMIT = 'submit';
    public const ACTION_ACTIVATE = 'activate';
    public const ACTION_RESUME = 'resume';
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_TERMINATE = 'terminate';
    public const ACTION_EXPIRE = 'expire';
}
