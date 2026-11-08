<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\ContentSystem\DataLoader;

use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductConfiguratorLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return ProductConfiguratorDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (!\array_key_exists('productId', $data)) {
            return new ProductConfiguratorLoaderConfig();
        }

        if (!\is_string($data['productId']) || $data['productId'] === '') {
            throw ProductException::invalidFieldValueType('productId', 'non-empty string', \gettype($data['productId']));
        }

        return new ProductConfiguratorLoaderConfig($data['productId']);
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductConfiguratorLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductConfiguratorLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
