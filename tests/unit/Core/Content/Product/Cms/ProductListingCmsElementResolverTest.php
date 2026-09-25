<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopwell\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopwell\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductListingCmsElementResolver::class)]
class ProductListingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = static::createStub(AbstractProductListingRoute::class);
        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        static::assertSame('product-listing', $resolver->getType());
    }

    public function testGetCollectReturnsNull(): void
    {
        $route = static::createStub(AbstractProductListingRoute::class);
        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([]);

        $slot = new CmsSlotEntity();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testEnrichHandlesDefaultSorting(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('filters', FieldConfig::SOURCE_STATIC, ['filter' => true]),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
                'defaultSorting' => [
                    'value' => 'sorting-id-1',
                ],
            ],
        ]);
        $request = new Request();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = static::createStub(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);
        static::assertInstanceOf(ProductListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    public function testEnrichHandlesAvailableSorting(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('filters', FieldConfig::SOURCE_STATIC, ['filter' => true]),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
            ],
        ]);
        $request = new Request([
            'availableSortings' => [
                'sorting-id' => 'sorting-id-1',
            ],
        ]);
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = static::createStub(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);
        static::assertInstanceOf(ProductListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    private function assertRequestPayload(Request $request): void
    {
        static::assertNull($request->request->get('property-whitelist'));
        static::assertTrue($request->request->get('manufacturer-filter'));
        static::assertTrue($request->request->get('rating-filter'));
        static::assertTrue($request->request->get('shipping-free-filter'));
        static::assertTrue($request->request->get('price-filter'));
        static::assertTrue($request->request->get('property-filter'));
        static::assertSame('expected-sorting', $request->request->get('order'));
    }
}
