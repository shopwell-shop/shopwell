<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\DependencyInjection\CompilerPass;

use Shopwell\Core\Content\Category\Service\NavigationLoader;
use Shopwell\Core\Content\Seo\HreflangLoaderInterface;
use Shopwell\Core\Content\Seo\SeoUrlUpdater;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 * Marks services public that would otherwise be inlined in setups where only Shopwell/Core is used,
 * as the only usages are in storefront
 */
class ContainerVisibilityCompilerPass implements CompilerPassInterface
{
    private const PUBLIC_TEST_SERVICES = [
        NavigationLoader::class,
        HreflangLoaderInterface::class,
        SeoUrlUpdater::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::PUBLIC_TEST_SERVICES as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $definition->setPublic(true);
        }
    }
}
