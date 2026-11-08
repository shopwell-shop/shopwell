<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Shipping\ContentSystem\DataLoader;

use Shopwell\Core\Checkout\Shipping\ShippingException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ShippingMethodLoaderConfigData from ShippingMethodLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ShippingMethodLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'shipping_method';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw ShippingException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw ShippingException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }
                $associations[] = $association;
            }
        }

        $onlyAvailable = true;
        if (\array_key_exists('onlyAvailable', $data)) {
            if (!\is_bool($data['onlyAvailable'])) {
                throw ShippingException::invalidFieldValueType('onlyAvailable', 'bool', \gettype($data['onlyAvailable']));
            }
            $onlyAvailable = $data['onlyAvailable'];
        }

        return new ShippingMethodLoaderConfig($associations, $onlyAvailable);
    }

    /**
     * @return ShippingMethodLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ShippingMethodLoaderConfig) {
            throw ShippingException::invalidFieldValueType('config', ShippingMethodLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
