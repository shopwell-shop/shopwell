<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductException;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopwell\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopwell\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Shopwell\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSuggestRoute::class)]
class ProductSuggestRouteTest extends TestCase
{
    /**
     * @var ProductListingLoader&MockObject
     */
    private ProductListingLoader $listingLoader;

    protected function setUp(): void
    {
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        $this->listingLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(new DecorationPatternException(ProductSuggestRoute::class));

        $this->getProductSuggestRoute()->getDecorated();
    }

    public function testLoadThrowsExceptionForMissingSearchParameter(): void
    {
        $this->listingLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(ProductException::missingRequestParameter('search'));

        $route = new ResolvedCriteriaProductSuggestRoute(
            static::createStub(ProductSearchBuilderInterface::class),
            new EventDispatcher(),
            static::createStub(AbstractProductSuggestRoute::class),
            new CompositeListingProcessor([])
        );

        $route->load(
            new Request(),
            static::createStub(SalesChannelContext::class),
            new Criteria()
        );
    }

    public function testLoadSuccessfully(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

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

        $this->getProductSuggestRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );
    }

    private function getProductSuggestRoute(): ProductSuggestRoute
    {
        return new ProductSuggestRoute(
            $this->listingLoader
        );
    }
}
