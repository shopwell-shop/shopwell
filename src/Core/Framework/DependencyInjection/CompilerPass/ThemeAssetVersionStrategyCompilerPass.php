<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class ThemeAssetVersionStrategyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasParameter('shopwell.filesystem.theme.use_last_modified_version_strategy')
            && !$container->getParameter('shopwell.filesystem.theme.use_last_modified_version_strategy')
        ) {
            $container->removeDefinition('shopwell.asset.theme.version_strategy');
            $container->setAlias('shopwell.asset.theme.version_strategy', 'assets.empty_version_strategy');
        }
    }
}
