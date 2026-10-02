<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DependencyInjection\CompilerPass;

use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\AbstractRouteScope;
use Shopwell\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class RouteScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeScopeDefinitions = $container->findTaggedServiceIds('shopwell.route_scope');

        $apiPrefixes = [];
        $apiContextPrefixes = [];
        foreach (array_keys($routeScopeDefinitions) as $definition) {
            $routeScope = $container->get($definition);

            if (!$routeScope instanceof AbstractRouteScope) {
                continue;
            }

            $apiPrefixes = array_merge($apiPrefixes, $routeScope->getRoutePrefixes());

            if ($routeScope instanceof ApiContextRouteScopeDependant) {
                $apiContextPrefixes = array_merge($apiContextPrefixes, $routeScope->getRoutePrefixes());
            }
        }

        $container->setParameter('shopwell.routing.registered_api_prefixes', $apiPrefixes);
        $container->setParameter('shopwell.routing.api_context_route_prefixes', array_values(array_unique($apiContextPrefixes)));
    }
}
