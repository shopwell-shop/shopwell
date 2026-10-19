<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationAck;
use Shopwell\Core\System\Payment\Notification\Struct\PaymentNotificationData;

/**
 * Reads the references out of an incoming channel notification.
 *
 * Extraction is deliberately independent of the credentials and of the signature: the platform has to
 * know *which* payment order a notification belongs to before it can look up the credentials that are
 * needed to verify it. Verifying and interpreting the notification is the job of the Notify action of
 * the channel.
 *
 * @internal
 */
#[Package('framework')]
interface PaymentNotificationExtractorInterface
{
    /**
     * @param array<string, string|list<string>> $headers
     */
    public function extract(string $rawBody, array $headers): PaymentNotificationData;

    /**
     * The answer the channel expects for a notification it sent. Anything else makes the channel
     * retry, so this is part of the channel and not of the controller.
     */
    public function acknowledge(): PaymentNotificationAck;
}
