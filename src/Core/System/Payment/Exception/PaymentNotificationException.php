<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Exception;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class PaymentNotificationException extends PaymentException
{
    public static function notificationInvalid(string $channel, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NOTIFICATION_INVALID,
            'The notification of channel "{{ channel }}" is invalid: {{ reason }}',
            ['channel' => $channel, 'reason' => $reason]
        );
    }

    public static function notificationOrderUnknown(string $channel, string $reference): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOTIFICATION_ORDER_UNKNOWN,
            'The notification of channel "{{ channel }}" references the unknown payment order "{{ reference }}".',
            ['channel' => $channel, 'reference' => $reference]
        );
    }

    public static function notificationNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOTIFICATION_NOT_FOUND,
            'The payment notification "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }

    public static function webhookDeliveryNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::WEBHOOK_DELIVERY_NOT_FOUND,
            'The payment webhook delivery "{{ id }}" does not exist.',
            ['id' => $id]
        );
    }
}
