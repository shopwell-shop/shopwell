<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The fixed business obligation, independent of the product and payer context used to execute it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentOrderRequest
{
    public function __construct(
        public string $outTradeNo,
        public int $amount,
        public string $currency,
        public string $subject,
        public ?string $body = null,
        public ?string $attach = null,
        public ?string $notifyUrl = null,
        public ?string $returnUrl = null,
        public ?int $expireMinutes = null,
        public ?string $clientIp = null,
        public ?string $idempotencyKey = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $outTradeNo = self::string($payload, 'out_trade_no');
        $currency = strtoupper(self::string($payload, 'currency'));
        $subject = self::string($payload, 'subject');

        $amount = $payload['amount'] ?? null;
        if (!\is_int($amount) && !(\is_string($amount) && ctype_digit($amount) && filter_var($amount, \FILTER_VALIDATE_INT) !== false)) {
            throw PaymentOrderException::invalidAmount(\is_scalar($amount) ? (int) $amount : 0);
        }

        $amount = (int) $amount;
        if ($amount <= 0) {
            throw PaymentOrderException::invalidAmount($amount);
        }

        return new self(
            outTradeNo: $outTradeNo,
            amount: $amount,
            currency: $currency,
            subject: $subject,
            body: self::optionalString($payload, 'body'),
            attach: self::optionalString($payload, 'attach'),
            notifyUrl: self::optionalString($payload, 'notify_url'),
            returnUrl: self::optionalString($payload, 'return_url'),
            expireMinutes: self::optionalInt($payload, 'expire_minutes'),
            clientIp: self::optionalString($payload, 'client_ip'),
            idempotencyKey: self::optionalString($payload, 'idempotency_key'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (!\is_scalar($value) || trim((string) $value) === '') {
            throw PaymentException::missingParameter($key);
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return \is_scalar($value) ? trim((string) $value) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function optionalInt(array $payload, string $key): ?int
    {
        $value = $payload[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return \is_numeric($value) ? (int) $value : null;
    }
}
