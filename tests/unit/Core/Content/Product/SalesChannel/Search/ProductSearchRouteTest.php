<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopwell\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductSearchRoute::class)]
class ProductSearchRouteTest extends TestCase
{
    /**
     * @var ProductListingLoader&MockObject
     */
    private ProductListingLoader $listingLoader;

    /**
     * @var ProductSearchBuilderInterface&MockObject
     */
    private ProductSearchBuilderInterface $searchBuilder;

    protected function setUp(): void
    {
        $this->searchBuilder = $this->createMock(ProductSearchBuilderInterface::class);
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        $this->searchBuilder->expects($this->never())->method('build');
        $this->listingLoader->expects($this->never())->method('load');

        static::expectException(DecorationPatternException::class);

        $this->getProductSearchRoute()->getDecorated();
    }

    public function testLoadWithSearchTerm(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

        $this->searchBuilder->expects($this->once())
            ->method('build')
            ->with(
                $request,
                $criteria,
                static::isInstanceOf(SalesChannelContext::class)
            );

        $this->listingLoader->expects($this->once())
            ->method('load')
            ->willReturn(new ProductListingResult(
                ProductDefinition::ENTITY_NAME,
                1,
                new ProductCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->getProductSearchRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );

        static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $criteria = new Criteria();

        $this->searchBuilder->expects($this->never())
            ->method('build');

        $this->listingLoader->expects($this->once())
            ->method('load')
            ->willReturn(new ProductListingResult(
                ProductDefinition::ENTITY_NAME,
                1,
                new ProductCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->getProductSearchRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );

        static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductSearchRouteResponse::class);

        $this->searchBuilder->expects($this->never())->method('build');
        $this->listingLoader->expects($this->never())->method('load');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-search-route.load.pre', static function (ProductSearchRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductSearchRoute(
            $this->searchBuilder,
            $this->listingLoader,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }

    private function getProductSearchRoute(): ProductSearchRoute
    {
        return new ProductSearchRoute(
            $this->searchBuilder,
            $this->listingLoader,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
