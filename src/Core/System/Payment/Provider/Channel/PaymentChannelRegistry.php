<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Channel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * Every channel of every payment interface, indexed by the name the api and the payment orders work
 * with.
 *
 * A channel name is unique across the interfaces on purpose: an app names the way it wants to be paid
 * (`alipay_h5`, `wechat_native`, …) and the platform derives the payment interface - and therefore the
 * merchant account - from it. Two interfaces offering the very same name would make that ambiguous, so
 * the registry refuses to be built at all.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentChannelRegistry
{
    /**
     * @var array<string, PaymentChannel>
     */
    private array $channels = [];

    /**
     * @throws PaymentException
     */
    public function __construct(PaymentProviderRegistry $providers)
    {
        foreach ($providers->all() as $provider) {
            foreach ($provider->channels as $channel) {
                $existing = $this->channels[$channel->technicalName] ?? null;

                if ($existing instanceof PaymentChannel) {
                    throw PaymentException::duplicateChannel($channel->technicalName, $existing->providerTechnicalName, $provider->technicalName);
                }

                $this->channels[$channel->technicalName] = $channel;
            }
        }
    }

    /**
     * @return array<string, PaymentChannel>
     */
    public function all(): array
    {
        return $this->channels;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->channels);
    }

    public function has(string $technicalName): bool
    {
        return isset($this->channels[$technicalName]);
    }

    /**
     * @throws PaymentException
     */
    public function get(string $technicalName): PaymentChannel
    {
        return $this->channels[$technicalName] ?? throw PaymentException::unknownChannel($technicalName);
    }

    /**
     * @return list<PaymentChannel>
     */
    public function ofProvider(string $providerTechnicalName): array
    {
        $channels = [];

        foreach ($this->channels as $channel) {
            if ($channel->providerTechnicalName === $providerTechnicalName) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }
}
