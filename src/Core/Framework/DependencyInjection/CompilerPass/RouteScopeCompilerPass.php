<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class RouteScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeScopeDefinitions = $container->findTaggedServiceIds('shopwell.route_scope');

        $apiPrefixes = [];
        foreach (array_keys($routeScopeDefinitions) as $definition) {
            $routeScope = $container->get($definition);

            if (!$routeScope instanceof AbstractRouteScope) {
                continue;
            }

            $apiPrefixes = array_merge($apiPrefixes, $routeScope->getRoutePrefixes());
        }

        $container->setParameter('shopwell.routing.registered_api_prefixes', $apiPrefixes);
    }
}
