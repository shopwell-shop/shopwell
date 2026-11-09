<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\ContentSystem\LayoutReference;
use Shopwell\Core\Framework\ContentSystem\Output\RenderResult;
use Shopwell\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopwell\Storefront\Controller\ContentController;
use Shopwell\Tests\Unit\Storefront\Controller\Stub\ContentControllerStub;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContentController::class)]
class ContentControllerTest extends TestCase
{
    private Stub&ProductListingRoute $productListingRouteMock;

    private ContentControllerStub $controller;

    protected function setUp(): void
    {
        $eventDispatcherMock = static::createStub(EventDispatcher::class);
        $this->productListingRouteMock = static::createStub(ProductListingRoute::class);

        $this->controller = new ContentControllerStub(
            $this->productListingRouteMock,
            static::createStub(ProductDetailRoute::class),
            static::createStub(ProductReviewLoader::class),
            static::createStub(FindProductVariantRoute::class),
            $eventDispatcherMock,
            new StaticSystemConfigService([
                'core.listing.showReview' => true,
            ]),
        );
    }

    public function testPageNoId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('id'));

        $this->controller->page(null, new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testWidgetRendersTheRequestedContentLayout(): void
    {
        $contentPage = ContentPage::fromRenderResult(new RenderResult([], LayoutReference::create('layout-id', 'Legal page', null), null));
        $this->controller->recorder()->contentPage = $contentPage;

        $response = $this->controller->page('layout-id', new Request(), Generator::generateSalesChannelContext());

        static::assertSame('/layout/layout-id', $this->controller->recorder()->loadedContentPath);
        static::assertSame('@Storefront/storefront/page/content/raw.html.twig', $this->controller->recorder()->renderStorefrontView);
        static::assertSame($contentPage, $this->controller->recorder()->renderStorefrontParameters['contentPage']);
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
    }

    public function testFullPageRendersTheRequestedContentLayout(): void
    {
        $contentPage = ContentPage::fromRenderResult(new RenderResult([], LayoutReference::create('layout-id', 'Legal page', null), null));
        $this->controller->recorder()->contentPage = $contentPage;

        $this->controller->pageFull('layout-id', new Request(), Generator::generateSalesChannelContext());

        static::assertSame('/layout/layout-id', $this->controller->recorder()->loadedContentPath);
        static::assertSame('@Storefront/storefront/page/content/page.html.twig', $this->controller->recorder()->renderStorefrontView);
        static::assertSame($contentPage, $this->controller->recorder()->renderStorefrontParameters['contentPage']);
    }

    public function testFilterReturn(): void
    {
        $ids = new IdsCollection();

        $testAggregations = new \ArrayObject([
            'count' => new CountResult('count', 2),
            'sum' => new SumResult('sum', 2.3),
        ]);
        $productListingResultMock = static::createStub(ProductListingResult::class);
        $productListingResultMock->method('getAggregations')->willReturn(
            new AggregationResultCollection(
                $testAggregations
            )
        );

        $request = new Request();

        $productListingRouteResponse = new ProductListingRouteResponse($productListingResultMock);
        $this->productListingRouteMock->method('load')->willReturn($productListingRouteResponse);

        $response = $this->controller->filter($ids->get('navigation'), $request, static::createStub(SalesChannelContext::class));

        static::assertSame(
            json_encode($testAggregations, \JSON_THROW_ON_ERROR),
            json_encode(json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR), \JSON_THROW_ON_ERROR)
        );

        static::assertTrue($request->request->get('only-aggregations'));
        static::assertTrue($request->request->get('reduce-aggregations'));
    }

    public function testSwitchReturn(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $this->controller->switchBuyBoxVariant($ids->get('product'), $request, static::createStub(SalesChannelContext::class));

        static::assertInstanceOf(SalesChannelProductEntity::class, $this->controller->recorder()->renderStorefrontParameters['product']);

        static::assertSame(
            $this->controller->recorder()->renderStorefrontParameters,
            [
                'product' => $this->controller->recorder()->renderStorefrontParameters['product'],
                'configuratorSettings' => null,
                'totalReviews' => 0,
                'elementId' => $ids->get('element'),
            ]
        );
    }

    public function testSwitchReturnWithoutReview(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $reviewLoader = $this->createMock(ProductReviewLoader::class);
        $reviewLoader->expects($this->never())->method('load');

        $controller = new ContentControllerStub(
            $this->productListingRouteMock,
            static::createStub(ProductDetailRoute::class),
            $reviewLoader,
            static::createStub(FindProductVariantRoute::class),
            static::createStub(EventDispatcher::class),
            new StaticSystemConfigService([
                'core.listing.showReview' => false,
            ]),
        );

        $context = Generator::generateSalesChannelContext();

        $controller->switchBuyBoxVariant($ids->get('product'), $request, $context);

        $reviewLoader = $this->createMock(ProductReviewLoader::class);
        $reviewLoader->expects($this->never())->method('load');

        // global config is enabled
        $systemConfig = new StaticSystemConfigService([
            'core.listing.showReview' => true,
        ]);

        // but disabled for current sales channel
        $systemConfig->set('core.listing.showReview', false, $context->getSalesChannelId());

        $controller = new ContentControllerStub(
            $this->productListingRouteMock,
            static::createStub(ProductDetailRoute::class),
            $reviewLoader,
            static::createStub(FindProductVariantRoute::class),
            static::createStub(EventDispatcher::class),
            $systemConfig
        );

        $controller->switchBuyBoxVariant($ids->get('product'), $request, $context);
    }

    public function testSwitchReturnWithReviews(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $result = static::createStub(ProductReviewResult::class);
        $result->method('getTotal')->willReturn(5);

        $reviewLoader = $this->createMock(ProductReviewLoader::class);
        $reviewLoader->expects($this->once())->method('load')->willReturn($result);

        $context = Generator::generateSalesChannelContext();

        // global config is enabled
        $systemConfig = new StaticSystemConfigService([
            'core.listing.showReview' => false,
        ]);

        // but enabled for current sales channel
        $systemConfig->set('core.listing.showReview', true, $context->getSalesChannelId());

        $controller = new ContentControllerStub(
            $this->productListingRouteMock,
            static::createStub(ProductDetailRoute::class),
            $reviewLoader,
            static::createStub(FindProductVariantRoute::class),
            static::createStub(EventDispatcher::class),
            $systemConfig
        );

        $controller->switchBuyBoxVariant($ids->get('product'), $request, $context);

        static::assertInstanceOf(SalesChannelProductEntity::class, $controller->recorder()->renderStorefrontParameters['product']);

        static::assertSame(
            $controller->recorder()->renderStorefrontParameters,
            [
                'product' => $controller->recorder()->renderStorefrontParameters['product'],
                'configuratorSettings' => null,
                'totalReviews' => 5,
                'elementId' => $ids->get('element'),
            ]
        );
    }

    public function testSwitchBuyBoxVariantWithInvalidJsonOptions(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => 'invalidJsonString',
            ]
        );

        $response = $this->controller->switchBuyBoxVariant(
            $ids->get('product'),
            $request,
            static::createStub(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
