<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class BlogListingLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return BlogListingDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (array_diff(array_keys($data), ['property']) !== []) {
            throw BlogException::invalidFieldValueType('config', 'property only', 'unknown configuration keys');
        }

        if (!isset($data['property']) || !\is_string($data['property']) || $data['property'] === '') {
            throw BlogException::invalidFieldValueType('property', 'non-empty-string', get_debug_type($data['property'] ?? null));
        }

        return new BlogListingLoaderConfig($data['property']);
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof BlogListingLoaderConfig) {
            throw BlogException::invalidFieldValueType('config', BlogListingLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
