<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * The notification and transaction messages are handed to the async queue, the gateway is already
 * resolved at that point and travels in the message.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SendSmsMessage implements AsyncMessageInterface
{
    public function __construct(public readonly SmsMessage $message)
    {
    }
}
