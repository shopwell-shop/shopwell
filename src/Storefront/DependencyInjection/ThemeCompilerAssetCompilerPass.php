<?php declare(strict_types=1);

namespace Shopwell\Storefront\DependencyInjection;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\ThemeCompiler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the `shopwell.asset`-tagged services into Storefront's ThemeCompiler as its `$packages` argument.
 *
 * Lives in Storefront (not Core) so Core stays unaware of ThemeCompiler. The pass is a no-op when ThemeCompiler is not registered.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeCompilerAssetCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ThemeCompiler::class)) {
            return;
        }

        $assets = [];
        foreach ($container->findTaggedServiceIds('shopwell.asset') as $id => $config) {
            $assets[$config[0]['asset']] = new Reference($id);
        }

        $container->getDefinition(ThemeCompiler::class)->setArgument('$packages', $assets);
    }
}
