<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentRefundStates;

/**
 * What a channel answered for a refund.
 *
 * @internal
 */
#[Package('framework')]
final readonly class RefundResult
{
    /**
     * @param string $status {@see PaymentRefundStates}: STATE_FINISHED when the channel refunded
     *                       synchronously, STATE_PROCESSING when the refund is settled asynchronously
     *                       and the platform has to wait for the notification
     */
    public function __construct(
        public string $status,
        public ?string $outRefundId = null,
    ) {
    }
}
