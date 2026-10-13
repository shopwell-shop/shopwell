<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The payment interfaces the platform ships, collected from the services tagged as
 * `shopwell.payment.provider`.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentProviderRegistry
{
    /**
     * @var array<string, PaymentProvider>
     */
    private array $providers = [];

    /**
     * @param iterable<PaymentProvider> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->technicalName] = $provider;
        }
    }

    /**
     * @return array<string, PaymentProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }

    public function has(string $technicalName): bool
    {
        return isset($this->providers[$technicalName]);
    }

    /**
     * @throws PaymentException
     */
    public function get(string $technicalName): PaymentProvider
    {
        return $this->providers[$technicalName] ?? throw PaymentException::unknownProvider($technicalName);
    }
}
