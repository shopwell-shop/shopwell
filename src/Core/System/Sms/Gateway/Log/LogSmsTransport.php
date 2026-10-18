<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway\Log;

use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Component\Notifier\Event\SentMessageEvent;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Writes the message to the log instead of sending it, so a message can be followed through the
 * pipeline during development without a provider account.
 *
 * The number is masked with the same normaliser the rest of the component uses, so a log never holds
 * a plain number. The body is not masked - a message that is only logged carries no secret an
 * administrator may not see.
 *
 * @internal
 */
#[Package('framework')]
final class LogSmsTransport implements TransportInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PhoneNumberNormalizer $normalizer,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function __toString(): string
    {
        return 'log';
    }

    public function send(MessageInterface $message): SentMessage
    {
        $this->logger->info('sms.message.logged', [
            'phone' => $this->normalizer->mask($message->getRecipientId() ?? ''),
            'subject' => $message->getSubject(),
        ]);

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
