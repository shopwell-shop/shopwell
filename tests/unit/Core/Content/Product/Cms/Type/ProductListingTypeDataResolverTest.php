<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopwell\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductListingCmsElementResolver::class)]
class ProductListingTypeDataResolverTest extends TestCase
{
    private ProductListingCmsElementResolver $listingResolver;

    protected function setUp(): void
    {
        $mock = static::createStub(ProductListingRoute::class);
        $mock->method('load')->willReturn(
            new ProductListingRouteResponse(
                new ProductListingResult('product', 0, new ProductCollection(), null, new Criteria(), Context::createDefaultContext())
            )
        );

        /** @var StaticEntityRepository<ProductSortingCollection> */
        $sortingRepository = new StaticEntityRepository([new ProductSortingCollection()]);

        $this->listingResolver = new ProductListingCmsElementResolver($mock, $sortingRepository);
    }

    public function testGetType(): void
    {
        static::assertSame('product-listing', $this->listingResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $collection = $this->listingResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutListingContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        $productListingStruct = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $productListingStruct);
        static::assertInstanceOf(EntitySearchResult::class, $productListingStruct->getListing());
    }
}
