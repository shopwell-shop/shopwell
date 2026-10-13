<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Message;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\LowPriorityMessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

/**
 * The marketing messages take the low priority queue, so they never hold back a verification code or
 * a delivery notification.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SendLowPrioritySmsMessage implements LowPriorityMessageInterface
{
    public function __construct(public readonly SmsMessage $message)
    {
    }
}
