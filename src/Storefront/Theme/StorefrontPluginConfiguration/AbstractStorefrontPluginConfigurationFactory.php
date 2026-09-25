<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\StorefrontPluginConfiguration;

use Shopwell\Core\Framework\Bundle;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractStorefrontPluginConfigurationFactory
{
    abstract public function getDecorated(): AbstractStorefrontPluginConfigurationFactory;

    abstract public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration;

    abstract public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function createFromThemeJson(string $name, array $data, string $path): StorefrontPluginConfiguration;
}
