<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment;

use Shopwell\Core\Framework\Log\Package;

/**
 * The state machine of a standalone payment order of the generic payment gateway.
 *
 * This is intentionally separate from OrderTransactionStates: the payment gateway serves external apps
 * and has no order, so the two life cycles never mix.
 */
#[Package('framework')]
final class PaymentOrderStates
{
    public const STATE_MACHINE = 'payment_order.state';

    public const STATE_CREATED = 'created';
    public const STATE_OPENED = 'opened';
    public const STATE_PAID = 'paid';
    public const STATE_FAILED = 'failed';
    public const STATE_CLOSED = 'closed';
    public const STATE_EXPIRED = 'expired';
    public const STATE_REFUNDED_PARTIALLY = 'refunded_partially';
    public const STATE_REFUNDED = 'refunded';

    public const ACTION_PROCESS = 'process';
    public const ACTION_PAY = 'pay';
    public const ACTION_FAIL = 'fail';
    public const ACTION_CLOSE = 'close';
    public const ACTION_EXPIRE = 'expire';
    public const ACTION_REFUND = 'refund';
    public const ACTION_REFUND_PARTIALLY = 'refund_partially';
}
