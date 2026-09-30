<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Routing\RouteScope;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RouteScopeCompilerPass::class)]
class RouteScopeCompilerPassTest extends TestCase
{
    public function testCollectsPrefixesOfAllRouteScopesAndOfApiContextRouteScopes(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ApiRouteScope::class, (new Definition(ApiRouteScope::class))->addTag('shopwell.route_scope'));
        $container->setDefinition(StoreApiRouteScope::class, (new Definition(StoreApiRouteScope::class))->addTag('shopwell.route_scope'));
        $container->setDefinition(RouteScope::class, (new Definition(RouteScope::class))->addTag('shopwell.route_scope'));

        (new RouteScopeCompilerPass())->process($container);

        static::assertSame(
            ['api', 'sw-domain-hash.html', 'store-api', '_wdt', '_profiler', '_error'],
            $container->getParameter('shopwell.routing.registered_api_prefixes')
        );
        static::assertSame(
            ['api', 'sw-domain-hash.html'],
            $container->getParameter('shopwell.routing.api_context_route_prefixes')
        );
    }
}
