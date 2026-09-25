<?php declare(strict_types=1);

namespace Shopwell\Core\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Shopwell\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
class ServiceClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly string $shopwellVersion,
    ) {
    }

    public function newFor(ServiceEntry $entry): ServiceClient
    {
        return new ServiceClient(
            $this->client->withOptions([
                'base_uri' => $entry->host,
            ]),
            $this->shopwellVersion,
            $entry,
        );
    }

    public function fromName(string $name): ServiceClient
    {
        return $this->newFor(
            $this->serviceRegistryClient->get($name)
        );
    }
}
