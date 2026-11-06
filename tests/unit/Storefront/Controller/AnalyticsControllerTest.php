<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Breadcrumb\BreadcrumbException;
use Shopwell\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopwell\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRouteResponse;
use Shopwell\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopwell\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Storefront\Controller\AnalyticsController;
use Shopwell\Storefront\Controller\Exception\StorefrontException;
use Shopwell\Storefront\System\SalesChannel\SalesChannelAnalyticsLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsController::class)]
class AnalyticsControllerTest extends TestCase
{
    public function testReturnsTheBreadcrumbNamesOfTheProduct(): void
    {
        $productId = Uuid::randomHex();
        $context = $this->contextWithAnalytics();

        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $request) => $request->attributes->get('id') === $productId
                    && $request->query->get('type') === 'product'),
                $context
            )
            ->willReturn(new BreadcrumbRouteResponse(new BreadcrumbCollection([
                new Breadcrumb('Damen', Uuid::randomHex()),
                new Breadcrumb('Schuhe', Uuid::randomHex()),
            ])));

        $response = $this->controller($route)->productCategories(new Request(['productId' => $productId]), $context);

        static::assertSame('["Damen","Schuhe"]', $response->getContent());
    }

    public function testReturnsNoCategoriesForAProductWithoutBreadcrumb(): void
    {
        $route = static::createStub(AbstractBreadcrumbRoute::class);
        $route->method('load')->willThrowException(BreadcrumbException::categoryNotFoundForProduct(Uuid::randomHex()));

        $response = $this->controller($route)->productCategories(
            new Request(['productId' => Uuid::randomHex()]),
            $this->contextWithAnalytics()
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('[]', $response->getContent());
    }

    public function testLetsAMissingProductThrough(): void
    {
        $error = BreadcrumbException::productNotFound(Uuid::randomHex());

        $route = static::createStub(AbstractBreadcrumbRoute::class);
        $route->method('load')->willThrowException($error);

        $this->expectExceptionObject($error);

        $this->controller($route)->productCategories(new Request(['productId' => Uuid::randomHex()]), $this->contextWithAnalytics());
    }

    public function testLetsABreadcrumbErrorOtherThanAMissingCategoryThrough(): void
    {
        $error = new BreadcrumbException(Response::HTTP_INTERNAL_SERVER_ERROR, 'BREADCRUMB_OTHER_ERROR', 'Another breadcrumb error');

        $route = static::createStub(AbstractBreadcrumbRoute::class);
        $route->method('load')->willThrowException($error);

        $this->expectExceptionObject($error);

        $this->controller($route)->productCategories(new Request(['productId' => Uuid::randomHex()]), $this->contextWithAnalytics());
    }

    public function testIsNotFoundForASalesChannelWithoutAnalytics(): void
    {
        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->never())->method('load');

        $this->expectExceptionObject(StorefrontException::analyticsNotActive());

        $this->controller($route)->productCategories(
            new Request(['productId' => Uuid::randomHex()]),
            Generator::generateSalesChannelContext()
        );
    }

    public function testIsNotFoundForASalesChannelWithInactiveAnalytics(): void
    {
        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->never())->method('load');

        $this->expectExceptionObject(StorefrontException::analyticsNotActive());

        $this->controller($route, false)->productCategories(
            new Request(['productId' => Uuid::randomHex()]),
            $this->contextWithAnalytics()
        );
    }

    public function testDoesNotCallTheRouteWithoutAValidProductId(): void
    {
        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->never())->method('load');

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('productId'));

        $this->controller($route)->productCategories(
            new Request(['productId' => 'not-an-id']),
            $this->contextWithAnalytics()
        );
    }

    private function contextWithAnalytics(): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setAnalyticsId(Uuid::randomHex());

        return $context;
    }

    private function controller(AbstractBreadcrumbRoute $route, bool $analyticsActive = true): AnalyticsController
    {
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId(Uuid::randomHex());
        $analytics->setActive($analyticsActive);

        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $controller = new AnalyticsController($route, new SalesChannelAnalyticsLoader($repository));
        $controller->setContainer(new ContainerBuilder());

        return $controller;
    }
}
