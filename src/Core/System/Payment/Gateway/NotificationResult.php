<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Gateway;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a channel notification says, after the signature of the channel was verified.
 *
 * @internal
 */
#[Package('framework')]
final readonly class NotificationResult
{
    /**
     * @param bool $signatureValid whether the notification really comes from the channel
     * @param bool $succeeded whether the channel reports the payment/refund as done
     * @param int|null $amount the amount the channel reports, in the smallest unit of the currency
     * @param array<string, mixed> $raw the payload of the channel, sanitized for the payment order
     */
    public function __construct(
        public bool $signatureValid,
        public bool $succeeded,
        public ?string $outPaymentNo = null,
        public ?string $outRefundId = null,
        public ?int $amount = null,
        public ?string $currency = null,
        public ?string $status = null,
        public array $raw = [],
    ) {
    }
}
