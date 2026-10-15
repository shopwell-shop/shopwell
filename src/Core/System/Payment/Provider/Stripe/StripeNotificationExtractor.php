<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Stripe;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;

/**
 * Reads the references of a stripe notification.
 *
 * Stripe posts a plain json body, so its references can be read without any credential: that is what
 * allows the platform to find out which app a notification belongs to and therefore whose signing
 * secret verifies it.
 *
 * The platform hands stripe its own payment number and refund number as metadata, because stripe only
 * carries `metadata` through from the creation of an object to its notification.
 *
 * @internal
 */
#[Package('framework')]
final class StripeNotificationExtractor implements PaymentNotificationExtractorInterface
{
    /**
     * The header stripe signs the raw body with, verified by the notify action of the interface.
     */
    final public const HEADER_SIGNATURE = 'stripe-signature';

    /**
     * The keys of the metadata stripe carries from the creation of an object to its notification.
     */
    final public const METADATA_PAYMENT_NO = 'payment_no';

    final public const METADATA_OUT_TRADE_NO = 'out_trade_no';

    final public const METADATA_REFUND_NO = 'refund_no';

    final public const METADATA_REASON = 'reason';

    public function extract(string $rawBody, array $headers): PaymentNotificationData
    {
        $event = json_decode($rawBody, true);

        if (!\is_array($event)) {
            return new PaymentNotificationData();
        }

        $object = \is_array($event['data'] ?? null) && \is_array($event['data']['object'] ?? null)
            ? $event['data']['object']
            : [];

        $type = \is_string($event['type'] ?? null) ? $event['type'] : '';
        $metadata = \is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        if (\in_array($type, StripeNotifyAction::REFUND_EVENTS, true)) {
            // the refund carries the refund number of the platform in its metadata; the payment number
            // is deliberately left empty, the refund in turn names its payment order
            return new PaymentNotificationData(
                refundNo: self::stringOf($metadata, self::METADATA_REFUND_NO),
                outPaymentNo: self::paymentIntentOf($object),
            );
        }

        return new PaymentNotificationData(
            paymentNo: self::stringOf($metadata, self::METADATA_PAYMENT_NO),
            outTradeNo: self::stringOf($metadata, self::METADATA_OUT_TRADE_NO),
            outPaymentNo: self::paymentIntentOf($object),
        );
    }

    /**
     * Stripe repeats a notification until it is acknowledged with a 2xx status, the body is ignored.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('', 'application/json');
    }

    /**
     * The payment intent of the object: a session only names it, a payment intent *is* it.
     *
     * @param array<string, mixed> $object
     */
    private static function paymentIntentOf(array $object): ?string
    {
        if (($object['object'] ?? null) === 'payment_intent') {
            return self::stringOf($object, 'id');
        }

        return self::stringOf($object, 'payment_intent');
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function stringOf(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return \is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
