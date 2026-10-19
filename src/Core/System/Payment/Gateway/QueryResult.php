<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a channel answers to the question what happened to a payment.
 *
 * The reconciliation asks the interface about a payment whose notification never arrived, and this is
 * the answer in the terms of the platform: the status says what should happen with the payment order,
 * everything else is what the pay path needs when the answer is `paid`. `absent` is an answer of its
 * own - the interface does not know the payment - which only turns into an expiry once the order is
 * allowed to expire.
 *
 * @internal
 */
#[Package('framework')]
final readonly class QueryResult
{
    final public const STATUS_PAID = 'paid';

    final public const STATUS_PENDING = 'pending';

    final public const STATUS_FAILED = 'failed';

    final public const STATUS_CLOSED = 'closed';

    /**
     * The interface does not know the payment - it was never opened, or it was opened under another
     * merchant account than the one that is asked.
     */
    final public const STATUS_ABSENT = 'absent';

    /**
     * @param string $status one of the STATUS_* constants, what the platform should do with the order
     * @param string|null $outPaymentNo the trade number of the channel, e.g. the alipay trade_no
     * @param int|null $amount the amount the channel reports, in the smallest unit of the currency
     * @param string|null $currency the currency of the amount, upper case
     * @param string|null $channelStatus the status in the words of the channel, e.g. the wechat
     *                                   trade_state, kept for support
     * @param array<string, mixed> $raw the payload of the channel, sanitized for the payment order
     */
    public function __construct(
        public string $status,
        public ?string $outPaymentNo = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $channelStatus = null,
        public array $raw = [],
    ) {
    }
}
