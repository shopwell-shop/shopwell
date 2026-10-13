<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway\Null;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Swallows a message but dispatches the events of a real send, so the delivery is still recorded and
 * the pipeline behaves the same whether sms is switched off, not configured yet or in a test.
 *
 * The notifier comes with a NullTransport of its own, but it replaces the message with a NullMessage
 * and the delivery record would lose the number and the template of the message.
 *
 * @internal
 */
#[Package('framework')]
final class NullSmsTransport implements TransportInterface
{
    public function __construct(private readonly ?EventDispatcherInterface $dispatcher = null)
    {
    }

    public function __toString(): string
    {
        return 'null';
    }

    public function send(MessageInterface $message): SentMessage
    {
        $sentMessage = new SentMessage($message, (string) $this);

        if ($this->dispatcher === null) {
            return $sentMessage;
        }

        $this->dispatcher->dispatch(new MessageEvent($message));
        $this->dispatcher->dispatch(new SentMessageEvent($sentMessage));

        return $sentMessage;
    }

    public function supports(MessageInterface $message): bool
    {
        return true;
    }
}
