<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * When telemetry metrics are globally disabled, services tagged with `shopwell.telemetry.subscriber`
 * or `shopwell.telemetry.periodic_metric_collector` are removed to avoid overhead.
 */
#[Package('framework')]
class TelemetrySubscriberCompilerPass implements CompilerPassInterface
{
    private const REMOVABLE_TAGS = [
        'shopwell.telemetry.subscriber',
        'shopwell.telemetry.periodic_metric_collector',
    ];

    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('shopwell.telemetry.metrics.enabled')) {
            return;
        }

        foreach (self::REMOVABLE_TAGS as $tag) {
            foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
                $container->removeDefinition($serviceId);
            }
        }
    }
}
