<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\BundleConfig;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\BundleConfigStyleFileResolver;
use Shopwell\Storefront\Theme\StorefrontPluginRegistry;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('discovery')]
final class StorefrontBundleConfigStyleFileResolver implements BundleConfigStyleFileResolver
{
    public function __construct(private readonly StorefrontPluginRegistry $registry)
    {
    }

    public function resolveStyleFiles(string $technicalName, string $basePath): array
    {
        $config = $this->registry->getConfigurations()->getByTechnicalName($technicalName);

        if ($config === null) {
            return [];
        }

        return array_values(array_map(
            static fn (string $path) => Path::join($basePath, 'Resources', $path),
            $config->getStyleFiles()->getFilepaths()
        ));
    }
}
