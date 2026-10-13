<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The payload of `POST /payment-api/v1/payment-orders/{paymentNo}/refunds`.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentRefundRequest
{
    public function __construct(
        public string $outRefundNo,
        public int $amount,
        public ?string $reason = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $outRefundNo = $payload['out_refund_no'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!\is_scalar($outRefundNo) || trim((string) $outRefundNo) === '') {
            throw PaymentException::missingParameter('out_refund_no');
        }

        if (!is_numeric($amount)) {
            throw PaymentException::missingParameter('amount');
        }

        $reason = $payload['reason'] ?? null;

        return new self(
            outRefundNo: trim((string) $outRefundNo),
            amount: (int) $amount,
            reason: \is_scalar($reason) && trim((string) $reason) !== '' ? trim((string) $reason) : null,
        );
    }
}
