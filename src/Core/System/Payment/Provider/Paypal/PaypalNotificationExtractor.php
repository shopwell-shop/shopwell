<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Paypal;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;

/**
 * Reads the references of a paypal notification.
 *
 * Paypal posts a plain json body, so its references can be read without any credential: that is what
 * allows the platform to find out which app a notification belongs to and therefore whose webhook id
 * verifies it.
 *
 * The platform hands paypal its own payment number as the custom id of the purchase unit and its own
 * refund number as the custom id of the refund, so both arrive back here unchanged.
 *
 * @internal
 */
#[Package('framework')]
final class PaypalNotificationExtractor implements PaymentNotificationExtractorInterface
{
    /**
     * The events that report something about a refund instead of about a payment.
     */
    private const REFUND_EVENT_MARKER = 'REFUND';

    public function extract(string $rawBody, array $headers): PaymentNotificationData
    {
        $event = json_decode($rawBody, true);

        if (!\is_array($event)) {
            return new PaymentNotificationData();
        }

        $resource = \is_array($event['resource'] ?? null) ? $event['resource'] : [];
        $eventType = \is_string($event['event_type'] ?? null) ? $event['event_type'] : '';

        if (str_contains($eventType, self::REFUND_EVENT_MARKER)) {
            // the refund number of the platform travels as the custom id of the refund; the payment
            // number is deliberately left empty, the refund in turn names its payment order
            return new PaymentNotificationData(
                outTradeNo: self::stringOf($resource, 'invoice_id'),
                outPaymentNo: self::stringOf($resource, 'id'),
                refundNo: self::stringOf($resource, 'custom_id'),
            );
        }

        return new PaymentNotificationData(
            paymentNo: self::stringOf($resource, 'custom_id'),
            outTradeNo: self::stringOf($resource, 'invoice_id'),
            outPaymentNo: self::stringOf($resource, 'id'),
        );
    }

    /**
     * Paypal repeats a notification until it is acknowledged with http 200, the body is ignored.
     */
    public function acknowledge(): PaymentNotificationAck
    {
        return new PaymentNotificationAck('', 'application/json');
    }

    /**
     * @param array<string, mixed> $resource
     */
    private static function stringOf(array $resource, string $key): ?string
    {
        $value = $resource[$key] ?? null;

        return \is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
