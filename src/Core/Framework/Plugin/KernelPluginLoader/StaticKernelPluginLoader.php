<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin\KernelPluginLoader;

use Composer\Autoload\ClassLoader;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
#[Package('framework')]
class StaticKernelPluginLoader extends KernelPluginLoader
{
    /**
     * @param list<PluginInfo> $plugins
     */
    public function __construct(
        ClassLoader $classLoader,
        ?string $pluginDir = null,
        array $plugins = []
    ) {
        parent::__construct($classLoader, $pluginDir);

        $this->pluginInfos = $plugins;
    }

    protected function loadPluginInfos(): void
    {
        // loaded in constructor
    }
}
