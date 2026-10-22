<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Exception\SmsUnrecoverableException;
use Shopwell\Core\System\Sms\Transport\SmsTransports;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * Sends a message that was put on the queue.
 *
 * The gateway was already resolved when the message was queued and travels as the name of the
 * message, so the handler does not have to run the routing again.
 *
 * A provider that refused the message for good is turned into an unrecoverable failure, so the queue
 * stops after the first attempt. Both transports are configured with three retries, and every retry
 * of a message the provider will refuse anyway only costs another round of requests. The delivery and
 * the log entry of the failure were already written by SmsDeliverySubscriber, the transports dispatch
 * the failure event before the exception reaches this handler.
 *
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler(handles: SendSmsMessage::class)]
#[AsMessageHandler(handles: SendLowPrioritySmsMessage::class)]
final class SendSmsHandler
{
    public function __construct(private readonly SmsTransports $transports)
    {
    }

    public function __invoke(SendSmsMessage|SendLowPrioritySmsMessage $envelope): ?SentMessage
    {
        try {
            return $this->transports->send($envelope->message);
        } catch (SmsUnrecoverableException $error) {
            throw new UnrecoverableMessageHandlingException($error->getMessage(), 0, $error);
        }
    }
}
