<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Adapter\Asset\AssetPackageService;
use Shopwell\Core\Framework\Bundle;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[Package('framework')]
class AssetBundleRegistrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var array<class-string<Bundle>> $bundles */
        // @phpstan-ignore varTag.type (ReflectionClass requires a class-string, but the bundles are already validated strings)
        $bundles = $container->getParameter('kernel.bundles');

        $assetService = $container->getDefinition('assets.packages');
        $assetService->setFactory([AssetPackageService::class, 'create']);

        $bundleMap = [];

        foreach ($bundles as $bundleClass) {
            $reflection = new \ReflectionClass($bundleClass);
            $bundle = $reflection->newInstanceWithoutConstructor();

            if ($bundle instanceof Bundle) {
                $bundleMap[$bundle->getName()] = $bundle->getPath();
            }
        }

        $arguments = $assetService->getArguments();
        array_unshift($arguments, new Reference('shopwell.asset.public.version_strategy'));
        array_unshift($arguments, new Reference('shopwell.asset.asset_without_versioning'));
        array_unshift($arguments, $bundleMap);

        $assetService->setArguments($arguments);
    }
}
