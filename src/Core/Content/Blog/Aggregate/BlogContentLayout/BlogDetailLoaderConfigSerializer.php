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
class BlogDetailLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return BlogDetailDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (array_diff(array_keys($data), ['property']) !== []) {
            throw BlogException::invalidFieldValueType('config', 'property only', 'unknown configuration keys');
        }

        if (!isset($data['property']) || !\is_string($data['property']) || $data['property'] === '') {
            throw BlogException::invalidFieldValueType('property', 'non-empty-string', get_debug_type($data['property'] ?? null));
        }

        return new BlogDetailLoaderConfig($data['property']);
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof BlogDetailLoaderConfig) {
            throw BlogException::invalidFieldValueType('config', BlogDetailLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
