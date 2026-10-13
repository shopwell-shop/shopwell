<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment;

use Shopwell\Core\Framework\Log\Package;

/**
 * The state machine of a refund of the generic payment gateway.
 */
#[Package('framework')]
final class PaymentRefundStates
{
    public const STATE_MACHINE = 'payment_refund.state';

    public const STATE_OPEN = 'open';
    public const STATE_PROCESSING = 'processing';
    public const STATE_FINISHED = 'finished';
    public const STATE_FAILED = 'failed';

    public const ACTION_PROCESS = 'process';
    public const ACTION_FINISH = 'finish';
    public const ACTION_FAIL = 'fail';
}
