<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Product\Cms\AbstractProductDetailCmsElementResolver;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractProductDetailCmsElementResolver::class)]
class AbstractProductDetailCmsElementResolverTest extends TestCase
{
    public function testCollectReturnsNullIfEntityResolverContextProvided(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product-id-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $product = new SalesChannelProductEntity();
        $product->setId('product-id-1');

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            $product,
        );

        $resolver = new TestProductDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);
        static::assertNull($collection);
    }

    public function testCollectReturnsNullIfNoConfigProvided(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new TestProductDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);
        static::assertNull($collection);
    }

    public function testCollectProductCriteria(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product-id-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new TestProductDetailCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(SalesChannelProductDefinition::class, $elements);

        $definition = $elements[SalesChannelProductDefinition::class];
        static::assertArrayHasKey('product_slot-1', $definition);

        $criteria = $definition['product_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame('cms::product-detail-static', $criteria->getTitle());
        static::assertArrayHasKey('properties', $criteria->getAssociations());
        static::assertArrayHasKey('group', $criteria->getAssociations()['properties']->getAssociations());
    }

    public function testGetSlotProductReturnsNullIfNoSearchResultProvided(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $data = new ElementDataCollection();
        $resolver = new TestProductDetailCmsElementResolver();

        static::assertNull($resolver->runGetSlotProduct($slot, $data, 'product-1'));
    }

    public function testGetSlotProduct(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');

        $entity = new SalesChannelProductEntity();
        $entity->setUniqueIdentifier('product-1');

        $collection = new SalesChannelProductCollection([$entity]);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        $data = new ElementDataCollection();
        $data->add('product_slot-1', $result);

        $resolver = new TestProductDetailCmsElementResolver();
        $product = $resolver->runGetSlotProduct($slot, $data, 'product-1');

        static::assertInstanceOf(SalesChannelProductEntity::class, $product);
    }
}
