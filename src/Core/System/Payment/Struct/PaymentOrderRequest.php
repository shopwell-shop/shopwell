<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The payload of `POST /payment-api/v1/payment-orders`, the business parameters without the auth
 * parameters.
 *
 * The channel is the way the app wants to be paid (`alipay_h5`, `wechat_native`, …); the payment
 * interface behind it is derived from the channel and never sent by the app.
 *
 * `channelParams` are the parameters of this very payment the channel demands at call time - the
 * `openid` of a wechat jsapi payment, for instance. They are declared as `runtime-params` of the
 * channel and are never stored as configuration, because they belong to the payer, not to the shop.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentOrderRequest
{
    /**
     * @param array<string, mixed> $channelParams the runtime parameters the channel needs for this payment
     */
    public function __construct(
        public string $outTradeNo,
        public string $channel,
        public int $amount,
        public string $currency,
        public string $subject,
        public ?string $body = null,
        public ?string $attach = null,
        public ?string $notifyUrl = null,
        public ?string $returnUrl = null,
        public ?int $expireMinutes = null,
        public ?string $clientIp = null,
        public ?string $device = null,
        public ?string $idempotencyKey = null,
        public array $channelParams = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $outTradeNo = self::string($payload, 'out_trade_no');
        $channel = self::string($payload, 'channel');
        $currency = strtoupper(self::string($payload, 'currency'));
        $subject = self::string($payload, 'subject');

        $amount = $payload['amount'] ?? null;
        if (!\is_int($amount) && !(\is_string($amount) && ctype_digit($amount))) {
            throw PaymentException::invalidAmount(\is_scalar($amount) ? (int) $amount : 0);
        }

        $amount = (int) $amount;
        if ($amount <= 0) {
            throw PaymentException::invalidAmount($amount);
        }

        return new self(
            outTradeNo: $outTradeNo,
            channel: $channel,
            amount: $amount,
            currency: $currency,
            subject: $subject,
            body: self::optionalString($payload, 'body'),
            attach: self::optionalString($payload, 'attach'),
            notifyUrl: self::optionalString($payload, 'notify_url'),
            returnUrl: self::optionalString($payload, 'return_url'),
            expireMinutes: self::optionalInt($payload, 'expire_minutes'),
            clientIp: self::optionalString($payload, 'client_ip'),
            device: self::optionalString($payload, 'device'),
            idempotencyKey: self::optionalString($payload, 'idempotency_key'),
            channelParams: self::paramsOf($payload, 'channel_params'),
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

    /**
     * The runtime parameters of the payment, flattened to scalar values: a channel validates them as
     * strings, so handing in an array would be rejected there anyway.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private static function paramsOf(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;

        if (!\is_array($value)) {
            return [];
        }

        $params = [];
        foreach ($value as $name => $item) {
            if (!\is_string($name) || !\is_scalar($item)) {
                continue;
            }

            $params[$name] = (string) $item;
        }

        return $params;
    }
}
