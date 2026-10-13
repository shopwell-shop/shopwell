<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Alipay;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Struct\PaymentNotificationData;

/**
 * Reads the references of an alipay notification.
 *
 * Alipay posts a plain form encoded body, so its references can be read without any credential: that
 * is what allows the platform to find out which app a notification belongs to and therefore which
 * public key verifies it.
 *
 * @internal
 */
#[Package('framework')]
final class AlipayNotificationExtractor implements PaymentNotificationExtractorInterface
{
    final public const PAYMENT_NO = 'out_trade_no';
    final public const OUT_PAYMENT_NO = 'trade_no';

    public function extract(string $rawBody, array $headers): PaymentNotificationData
    {
        $parsed = [];
        parse_str($rawBody, $parsed);

        return new PaymentNotificationData(
            paymentNo: self::scalarOf($parsed, self::PAYMENT_NO),
            outPaymentNo: self::scalarOf($parsed, self::OUT_PAYMENT_NO),
        );
    }

    /**
     * Alipay repeats a notification until it reads the plain word `success`.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('success');
    }

    /**
     * The alipay notification is a form encoded body, so every value `parse_str` reads is a string.
     *
     * @param array<int|string, array<mixed>|string> $parsed
     */
    private static function scalarOf(array $parsed, string $key): ?string
    {
        $value = $parsed[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
