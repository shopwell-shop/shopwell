<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\CmsPageEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopwell\Core\Content\Product\Cms\ProductBoxCmsElementResolver;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\PropertyNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductBoxCmsElementResolver::class)]
class ProductBoxTypeDataResolverTest extends TestCase
{
    private ProductBoxCmsElementResolver $productBoxResolver;

    private Stub&SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->systemConfig = static::createStub(SystemConfigService::class);
        $this->productBoxResolver = new ProductBoxCmsElementResolver($this->systemConfig);
    }

    public function testType(): void
    {
        static::assertSame('product-box', $this->productBoxResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        static::assertSame(['product123'], $criteriaCollection->all()[ProductDefinition::class]['product_id']->getIds());
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'entity.relatedProduct'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);
        static::assertNull($productBoxStruct->getProductId());
        static::assertNull($productBoxStruct->getProduct());
    }

    #[DataProvider('enrichWithStaticConfigProvider')]
    public function testEnrichWithStaticConfig(bool $closeout, bool $hidden, int $availableStock): void
    {
        if ($hidden) {
            $this->systemConfig->method('getBool')->willReturn(true);
        }

        $salesChannelId = 'f3489c46df62422abdea4aa1bb03511c';

        $product = new SalesChannelProductEntity();
        $product->setId('product123');
        $product->setStock($availableStock);
        $product->setIsCloseout($closeout);
        $product->setAvailableStock($availableStock);
        $product->internalSetEntityData('product', new FieldVisibility([]));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $resolverContext = new ResolverContext($salesChannelContext, new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection([clone $product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $slot = $this->enrichProductWithCmsConfig($product);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);
        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);

        /*
         * conditional assertions depending on if a product should be returned or not
         */
        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($productBoxStruct->getProductId());
        } else {
            static::assertSame($productBoxStruct->getProductId(), $product->getId());
            static::assertNotSame($product, $productBoxStruct->getProduct());

            $serializedProduct = json_encode($product);
            static::assertNotSame(\JSON_ERROR_RECURSION, json_last_error());
            static::assertNotFalse($serializedProduct);
        }
    }

    /**
     * @return iterable<string, array{closeout: bool, hidden: bool, availableStock: int}>
     */
    public static function enrichWithStaticConfigProvider(): iterable
    {
        yield 'visible product with stock is enriched' => ['closeout' => false, 'hidden' => false, 'availableStock' => 1];
        yield 'hidden product with stock is enriched when closeout is disabled' => ['closeout' => false, 'hidden' => true,  'availableStock' => 1];
        yield 'closeout product with stock is enriched when it is visible' => ['closeout' => true, 'hidden' => false, 'availableStock' => 1];
        yield 'hidden closeout product with stock is enriched' => ['closeout' => true, 'hidden' => true,  'availableStock' => 1];
        yield 'hidden closeout product without stock is not enriched' => ['closeout' => true, 'hidden' => true,  'availableStock' => 0];
    }

    public function testEnrichWithStaticConfigButNoResult(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);
        static::assertNull($productBoxStruct->getProductId());
        static::assertNull($productBoxStruct->getProduct());
    }

    public function testEnrichWithMappedConfig(): void
    {
        $parent = new SalesChannelProductEntity();
        $parent->setId('product_parent');

        $product = new SalesChannelProductEntity();
        $product->setId('product123');
        $product->setParent($parent);

        $resolverContext = new EntityResolverContext(static::createStub(SalesChannelContext::class), new Request(), static::createStub(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'product.parent'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);

        $productBoxStruct = $slot->getData();
        static::assertInstanceOf(ProductBoxStruct::class, $productBoxStruct);
        static::assertSame($parent->getId(), $productBoxStruct->getProductId());
    }

    public function testEnrichWithMappedConfigButInvalidProperty(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product123');

        $resolverContext = new EntityResolverContext(static::createStub(SalesChannelContext::class), new Request(), static::createStub(ProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, 'product.foo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $this->expectExceptionObject(new PropertyNotFoundException('foo', SalesChannelProductEntity::class));

        $this->productBoxResolver->enrich($slot, $resolverContext, $result);
    }

    public function testCollectWithEmptyProductId(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->productBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    protected function enrichProductWithCmsConfig(SalesChannelProductEntity $product): CmsSlotEntity
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, $product->getId()));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setSlot('product-box');
        $slot->setType('product-box');
        $slot->setFieldConfig($fieldConfig);
        $slot->getFieldConfig();

        $cmsBlock = new CmsBlockEntity();
        $cmsBlock->setUniqueIdentifier('block1');
        $cmsBlock->setSlots(new CmsSlotCollection([$slot]));

        $cmsSection = new CmsSectionEntity();
        $cmsSection->setUniqueIdentifier('section1');
        $cmsSection->setBlocks(new CmsBlockCollection([$cmsBlock]));

        $cmsPage = new CmsPageEntity();
        $cmsPage->setSections(new CmsSectionCollection([$cmsSection]));

        $product->setCmsPage($cmsPage);

        return $slot;
    }
}
