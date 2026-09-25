<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class FeatureFlagCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $featureFlags = $container->getParameter('shopwell.feature.flags');
        if (!\is_array($featureFlags)) {
            throw new \RuntimeException('Container parameter "shopwell.feature.flags" needs to be an array');
        }

        Feature::registerFeatures($featureFlags);

        foreach ($container->findTaggedServiceIds('shopwell.feature') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['flag'])) {
                    throw new \RuntimeException('"flag" is a required field for "shopwell.feature" tags');
                }

                if (Feature::isActive($tag['flag'])) {
                    continue;
                }

                $container->removeDefinition($serviceId);

                break;
            }
        }
    }
}
