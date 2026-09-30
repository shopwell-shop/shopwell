<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class FeatureFlagCompilerPass implements CompilerPassInterface
{
    public const ALIASES_TO_REMOVE = [
        'v6.8.0.0' => [
            'Shopwell\Administration\Controller\NotificationController',
            'Shopwell\Administration\Notification\NotificationDefinition',
            'Shopwell\Core\Content\ProductStream\Service\ProductStreamBuilderInterface',
            'Shopwell\Core\Framework\Plugin\Util\AssetService',
            'Shopwell\Elasticsearch\Product\SearchConfigLoader',
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        $featureFlags = $container->getParameter('shopwell.feature.flags');
        if (!\is_array($featureFlags)) {
            throw DependencyInjectionException::parameterHasWrongType('shopwell.feature.flags', 'array', get_debug_type($featureFlags));
        }

        Feature::registerFeatures($featureFlags);

        foreach ($container->findTaggedServiceIds('shopwell.feature') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['flag'])) {
                    throw DependencyInjectionException::featureTagMissingFlag($serviceId, 'shopwell.feature');
                }

                if (Feature::isActive($tag['flag'])) {
                    continue;
                }

                $container->removeDefinition($serviceId);

                break;
            }
        }

        foreach ($container->findTaggedServiceIds('shopwell.inactiveFeature') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (!isset($tag['flag'])) {
                    throw DependencyInjectionException::featureTagMissingFlag($serviceId, 'shopwell.inactiveFeature');
                }

                if (!Feature::has($tag['flag']) || !Feature::isActive($tag['flag'])) {
                    continue;
                }

                $container->removeDefinition($serviceId);

                break;
            }
        }

        foreach (self::ALIASES_TO_REMOVE as $flag => $aliases) {
            if (!Feature::has($flag) || !Feature::isActive($flag)) {
                continue;
            }

            foreach ($aliases as $aliasId) {
                $container->removeAlias($aliasId);
            }
        }
    }
}
