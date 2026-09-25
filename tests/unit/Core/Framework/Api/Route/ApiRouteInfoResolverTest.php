<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiRouteInfoResolver::class)]
class ApiRouteInfoResolverTest extends TestCase
{
    private ApiRouteInfoResolver $apiRouteInfoResolver;

    private RouterInterface&MockObject $routerInterface;

    protected function setUp(): void
    {
        $this->routerInterface = $this->createMock(RouterInterface::class);
        $this->apiRouteInfoResolver = new ApiRouteInfoResolver($this->routerInterface);
    }

    public function testResolveRouteInfo(): void
    {
        $routeCollection = new RouteCollection();
        $route1 = new Route(path: '/route1', defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]], methods: ['GET', 'POST']);
        $routeCollection->add('route1', $route1);

        $route2 = new Route(path: '/route2', defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]], methods: ['POST']);
        $routeCollection->add('route2', $route2);

        $route3 = new Route(path: '/route3', methods: ['POST']);
        $routeCollection->add('route3', $route3);

        $route4 = new Route(
            path: '/route4',
            defaults: [
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID],
                PlatformRequest::ATTRIBUTE_OPENAPI => false,
            ],
            methods: ['DELETE']
        );
        $routeCollection->add('route4', $route4);

        $this->routerInterface->expects($this->once())
            ->method('getRouteCollection')
            ->willReturn($routeCollection);

        $routeInfo = $this->apiRouteInfoResolver->getApiRoutes(ApiRouteScope::ID);
        static::assertCount(2, $routeInfo);
        static::assertSame($route1->getPath(), $routeInfo[0]->path);
        static::assertSame($route1->getMethods(), $routeInfo[0]->methods);
        static::assertSame($route4->getPath(), $routeInfo[1]->path);
        static::assertSame($route4->getMethods(), $routeInfo[1]->methods);
    }
}
