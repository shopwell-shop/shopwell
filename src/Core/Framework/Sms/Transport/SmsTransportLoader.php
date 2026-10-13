<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Transport;

use Doctrine\DBAL\Exception\DriverException;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayCollection;
use Shopwell\Core\Framework\Sms\DataAbstractionLayer\SmsGatewayEntity;
use Shopwell\Core\Framework\Sms\Gateway\Null\NullSmsTransport;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayResolverInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the transport of a gateway.
 *
 * It mirrors the MailerTransportLoader of the platform: the credentials do not come from a dsn in the
 * environment but from an sms_gateway row an administrator maintains, because credentials have to be
 * configurable per sales channel and several accounts of one provider have to live next to each
 * other, which a dsn cannot express without leaking the secret into the container parameters.
 *
 * @internal
 */
#[Package('framework')]
class SmsTransportLoader
{
    /**
     * @param EntityRepository<SmsGatewayCollection> $gatewayRepository
     */
    public function __construct(
        private readonly SmsGatewayRegistry $registry,
        private readonly EntityRepository $gatewayRepository,
        private readonly LoggerInterface $logger,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * The factory of the texter transports, the counterpart of MailerTransportLoader::fromStrings().
     *
     * The dsns of framework.notifier.texter_transports are not used, the gateways are resolved from
     * the database per message - a provider of a static dsn can be registered as a gateway factory
     * just like a provider that is implemented here.
     *
     * @param array<string, string> $dsns
     */
    public function fromConfig(array $dsns): SmsTransports
    {
        return new SmsTransports($this);
    }

    public function fromGatewayId(string $gatewayId): TransportInterface
    {
        if ($gatewayId === SmsGatewayResolverInterface::GATEWAY_NULL) {
            return new NullSmsTransport($this->dispatcher);
        }

        try {
            $gateway = $this->gatewayRepository
                ->search(new Criteria([$gatewayId]), Context::createDefaultContext())
                ->getEntities()
                ->first();
        } catch (DriverException) {
            // the database is not reachable, this happens during the installation
            return new NullSmsTransport($this->dispatcher);
        }

        if (!$gateway instanceof SmsGatewayEntity || !$gateway->enabled) {
            return new NullSmsTransport($this->dispatcher);
        }

        if (!$this->registry->has($gateway->technicalName)) {
            $this->logger->error('The sms gateway of the message is not registered, the message is not sent.', [
                'technicalName' => $gateway->technicalName,
                'gatewayId' => $gatewayId,
            ]);

            return new NullSmsTransport($this->dispatcher);
        }

        return $this->registry
            ->get($gateway->technicalName)
            ->create($gateway->credentials ?? [], $gateway->options ?? [], $this->dispatcher);
    }
}
