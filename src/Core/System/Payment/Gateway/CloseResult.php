<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a channel answers when the platform closes a payment.
 *
 * The three answers the platform acts on are all normal answers of the channel, not errors: the trade
 * is closed, the trade cannot be closed because it was already paid, or the channel does not know the
 * trade at all. They are kept apart because they mean very different things for the order - a closed
 * or unknown trade may be closed locally, a paid one may not, or the payment would be lost.
 *
 * @internal
 */
#[Package('framework')]
final readonly class CloseResult
{
    /**
     * The trade is closed at the channel, no payment can arrive anymore.
     */
    final public const STATUS_CLOSED = 'closed';

    /**
     * The trade cannot be closed because it is already paid - the money is on its way or already
     * there.
     */
    final public const STATUS_ALREADY_PAID = 'already_paid';

    /**
     * The channel does not know the trade - nothing can be paid on it anymore.
     */
    final public const STATUS_NOT_FOUND = 'not_found';

    /**
     * @param string $status one of the STATUS_* constants
     * @param string|null $channelStatus the status in the words of the channel, kept for support
     * @param array<string, mixed> $raw the payload of the channel, sanitized for the payment order
     */
    public function __construct(
        public string $status,
        public ?string $channelStatus = null,
        public array $raw = [],
    ) {
    }
}
