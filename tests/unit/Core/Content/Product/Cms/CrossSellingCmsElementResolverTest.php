<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\CrossSellingStruct;
use Shopwell\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Shopwell\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopwell\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElement;
use Shopwell\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopwell\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CrossSellingCmsElementResolver::class)]
class CrossSellingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = static::createStub(AbstractProductCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);
        static::assertSame('cross-selling', $resolver->getType());
    }

    public function testEnrichStaticSlotWithCrossSelling(): void
    {
        $productId = 'product-1';

        $response = new ProductCrossSellingRouteResponse(new CrossSellingElementCollection([
            (new CrossSellingElement())->assign(['total' => 1]),
        ]));

        $route = static::createStub(AbstractProductCrossSellingRoute::class);
        $route->method('load')->willReturn($response);

        $resolver = new CrossSellingCmsElementResolver($route);
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new SalesChannelProductCollection([$product]));

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $data);

        $collection = $data->getCrossSellings();
        static::assertInstanceOf(CrossSellingElementCollection::class, $collection);

        $crossSelling = $collection->first();
        static::assertInstanceOf(CrossSellingElement::class, $crossSelling);
        static::assertSame(1, $crossSelling->getTotal());
    }

    public function testEnrichSetsEmptyCrossSellingWithoutConfig(): void
    {
        $route = static::createStub(AbstractProductCrossSellingRoute::class);
        $resolver = new CrossSellingCmsElementResolver($route);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());
        $data = new ElementDataCollection();

        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(CrossSellingStruct::class, $data);
        static::assertNull($data->getCrossSellings());
    }
}
