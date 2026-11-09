<?php declare(strict_types=1);

namespace Shopwell\Storefront\ContentSystem\Hydration\DataLoader;

use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class FormGendersLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return FormGendersDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if ($data !== []) {
            throw ContentSystemException::invalidFieldValueType('config', 'empty map', 'non-empty map');
        }

        return new FormGendersLoaderConfig();
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof FormGendersLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', FormGendersLoaderConfig::class, $config::class);
        }

        return [];
    }
}
