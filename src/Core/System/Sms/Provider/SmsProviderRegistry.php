<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;

/**
 * @internal
 */
#[Package('framework')]
final class SmsProviderRegistry
{
    /**
     * @var array<string, AbstractSmsProvider>
     */
    private array $providers = [];

    /**
     * @param iterable<AbstractSmsProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $name = $provider->getTechnicalName();

            if (trim($name) === '' || isset($this->providers[$name])) {
                throw SmsException::invalidProviderDeclaration($name);
            }

            $this->providers[$name] = $provider;
        }
    }

    public function has(string $technicalName): bool
    {
        return isset($this->providers[$technicalName]);
    }

    public function get(string $technicalName): AbstractSmsProvider
    {
        return $this->providers[$technicalName]
            ?? throw SmsException::unknownGateway($technicalName, array_keys($this->providers));
    }

    /**
     * @return array<string, AbstractSmsProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
