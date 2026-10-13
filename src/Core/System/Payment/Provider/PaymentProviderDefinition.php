<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;

/**
 * What the `payment-provider.xml` of a payment interface declares: its name, its label and the shape of
 * its configuration.
 *
 * The definition is pure data and knows nothing about php services - the credentials form and the list
 * of channels are the same whether the interface is shipped by the platform or by an extension. The
 * gateway factory and the notification extractor are bound to it in {@see PaymentProvider}.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PaymentProviderDefinition
{
    /**
     * @param list<PaymentConfigField> $credentials the fields of the merchant account of the interface
     * @param list<PaymentChannel> $channels the ways of paying the interface offers
     */
    public function __construct(
        public string $technicalName,
        public string $label,
        public array $credentials,
        public array $channels,
    ) {
    }

    public function credentialField(string $name): ?PaymentConfigField
    {
        foreach ($this->credentials as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function hasChannel(string $technicalName): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->technicalName === $technicalName) {
                return true;
            }
        }

        return false;
    }
}
