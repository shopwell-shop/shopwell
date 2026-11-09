<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\ContentSystem\DataLoader;

use Shopwell\Core\Content\Category\CategoryException;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ServiceMenuLoaderConfigData from ServiceMenuLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ServiceMenuLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'service_menu';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $rootId = null;
        if (\array_key_exists('rootId', $data)) {
            if (!\is_string($data['rootId']) || $data['rootId'] === '') {
                throw CategoryException::invalidFieldValueType('rootId', 'non-empty string', \gettype($data['rootId']));
            }
            $rootId = $data['rootId'];
        }

        return new ServiceMenuLoaderConfig($rootId);
    }

    /**
     * @return ServiceMenuLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ServiceMenuLoaderConfig) {
            throw CategoryException::invalidFieldValueType('config', ServiceMenuLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
