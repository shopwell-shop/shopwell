<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Transport;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayResolverInterface;
use Shopwell\Core\Framework\Sms\SmsException;
use Shopwell\Core\Framework\Sms\SmsUnrecoverableException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**
 * The transports of the text channel, resolved lazily by the name the message carries.
 *
 * A name is the id of an sms_gateway row. The resolution is postponed to the first send, so a
 * request that sends no message never touches the database and never builds a http client, and the
 * transports built during one request are reused.
 *
 * The notifier ships a Transports class doing the lookup by name too, but it is final and takes its
 * transports eagerly.
 *
 * @internal
 */
#[Package('framework')]
final class SmsTransports implements TransportInterface
{
    /**
     * @var array<string, TransportInterface>
     */
    private array $transports = [];

    public function __construct(private readonly SmsTransportLoader $loader)
    {
    }

    public function __toString(): string
    {
        return 'sms-transports';
    }

    /**
     * The transports throw while the message travels: SmsException says the provider may take the
     * same message later, SmsUnrecoverableException that it never will. Both are documented here so
     * a caller knows what it has to catch, the notifier itself declares neither.
     *
     * @throws SmsException
     * @throws SmsUnrecoverableException
     */
    public function send(MessageInterface $message): ?SentMessage
    {
        $name = $message->getTransport();

        return $this->transport($name === null || $name === '' ? SmsGatewayResolverInterface::GATEWAY_NULL : $name)
            ->send($message);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    private function transport(string $name): TransportInterface
    {
        return $this->transports[$name] ??= $this->loader->fromGatewayId($name);
    }
}
