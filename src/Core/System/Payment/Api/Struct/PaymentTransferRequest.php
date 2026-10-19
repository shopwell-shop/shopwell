<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The payload of a transfer: one payout to one recipient.
 *
 * It exists instead of a bare array for the same reason the payment order has one - the service is
 * the only place that validates a payout, and a DTO keeps the shape of the call out of the controller.
 * `recipientId` refers to a row created through {@see \Shopwell\Core\System\Payment\Transfer\PaymentTransferRecipientService}.
 *
 * The provider is sent by the caller although the channel usually implies it: paying out is not a
 * checkout, there is no channel registry entry that collects, and the provider decides the identity
 * matrix, the currency and the flow the payout runs through.
 *
 * `amount` is an integer in the smallest unit of the currency (fen for CNY). It is never converted
 * here - the exchange rate and the decimal formatting of the interface belong to the channel adapter.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentTransferRequest
{
    /**
     * @param array<string, mixed>|null $metadata free form data the app keeps with the payout
     * @param array<string, mixed> $channelParams the parameters of the very channel the payout
     *                                            runs through - alipay's `transfer_scene_name`
     *                                            and `transfer_scene_report_infos`, for
     *                                            instance. They only reach the channel adapter,
     *                                            the service never reads them.
     */
    public function __construct(
        public string $outNo,
        public string $recipientId,
        public string $provider,
        public string $channel,
        public int $amount,
        public string $currency,
        public ?array $metadata = null,
        public array $channelParams = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws PaymentException
     */
    public static function fromArray(array $payload): self
    {
        $amount = $payload['amount'] ?? null;
        if (!\is_int($amount) && !(\is_string($amount) && ctype_digit($amount))) {
            throw PaymentException::transferAmountInvalid();
        }

        $metadata = $payload['metadata'] ?? null;

        return new self(
            outNo: self::string($payload, 'out_no'),
            recipientId: self::string($payload, 'recipient_id'),
            provider: self::string($payload, 'provider'),
            channel: self::string($payload, 'channel'),
            amount: (int) $amount,
            currency: strtoupper(self::string($payload, 'currency')),
            metadata: \is_array($metadata) ? $metadata : null,
            channelParams: self::channelParamsOf($payload),
        );
    }

    /**
     * The channel parameters of the payout, kept as they came - what a channel makes of them is its
     * own business: `transfer_scene_report_infos` of alipay is a list of lists, not a flat map.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function channelParamsOf(array $payload): array
    {
        $value = $payload['channel_params'] ?? null;

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws PaymentException
     */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!\is_scalar($value) || trim((string) $value) === '') {
            throw PaymentException::missingParameter($key);
        }

        return trim((string) $value);
    }
}
