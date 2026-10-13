<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Gateway;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\SmsException;

/**
 * Indexes the gateway factories the platform and the extensions registered, keyed by their
 * technical name.
 *
 * @internal
 */
#[Package('framework')]
final class SmsGatewayRegistry
{
    /**
     * @var array<string, SmsGatewayTransportFactoryInterface>
     */
    private array $factories = [];

    /**
     * @param iterable<SmsGatewayTransportFactoryInterface> $factories
     */
    public function __construct(iterable $factories)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getTechnicalName()] = $factory;
        }
    }

    public function has(string $technicalName): bool
    {
        return isset($this->factories[$technicalName]);
    }

    /**
     * @throws SmsException
     */
    public function get(string $technicalName): SmsGatewayTransportFactoryInterface
    {
        return $this->factories[$technicalName]
            ?? throw SmsException::unknownGateway($technicalName, array_keys($this->factories));
    }

    /**
     * @return array<string, SmsGatewayTransportFactoryInterface>
     */
    public function all(): array
    {
        return $this->factories;
    }
}
