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
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ManufacturerLogoStruct;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopwell\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
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
#[CoversClass(ManufacturerLogoCmsElementResolver::class)]
class ManufacturerLogoCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertSame('manufacturer-logo', $resolver->getType());
    }

    public function testCollectCreatesMediaCriteria(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(MediaDefinition::class, $elements);

        $definitionData = array_shift($elements);
        static::assertCount(1, $definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = array_shift($definitionData);
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testCollectReturnsNullWithEmptyConfig(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, null),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectionWithMediaIsEmptyAndManufacturerIsNotLoaded(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $product = new SalesChannelProductEntity();
        $product->setId('product-1');
        $product->setManufacturerId('manufacturer-1');

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            $product
        );

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);
        static::assertArrayHasKey(ProductManufacturerDefinition::class, $collection->all());
        $criteria = $collection->all()[ProductManufacturerDefinition::class]['mapped_product_manufacturer_slot-1'];
        static::assertSame(['manufacturer-1'], $criteria->getIds());
        static::assertArrayHasKey('media', $criteria->getAssociations());
    }

    public function testCollectionWithMediaIsEmptyAndManufacturerIsLoadedAndHasNoMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-1');

        $product = new SalesChannelProductEntity();
        $product->setId('product-1');
        $product->setManufacturerId('manufacturer-1');
        $product->setManufacturer($manufacturer);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            $product
        );

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);
        static::assertArrayHasKey(ProductManufacturerDefinition::class, $collection->all());
        $criteria = $collection->all()[ProductManufacturerDefinition::class]['mapped_product_manufacturer_slot-1'];
        static::assertSame(['manufacturer-1'], $criteria->getIds());
        static::assertArrayHasKey('media', $criteria->getAssociations());
    }

    public function testCollectSkipsMappedProductCriteriaWhenMappedMediaAlreadyLoaded(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $media = new MediaEntity();
        $media->setId('media-1');

        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-1');
        $manufacturer->setMedia($media);

        $product = new SalesChannelProductEntity();
        $product->setId('product-1');
        $product->setManufacturerId('manufacturer-1');
        $product->setManufacturer($manufacturer);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            $product
        );

        $resolver = new ManufacturerLogoCmsElementResolver();
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsParentCriteriaWhenMediaIsMappedAndProductIsStatic(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'),
            new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new ManufacturerLogoCmsElementResolver();
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);
        static::assertArrayHasKey(SalesChannelProductDefinition::class, $collection->all());
        static::assertArrayHasKey('product_slot-1', $collection->all()[SalesChannelProductDefinition::class]);
    }

    public function testEnrichStaticSlotWithManufacturerLogo(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
            new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://localhost'),
            new FieldConfig('newTab', FieldConfig::SOURCE_STATIC, true),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new MediaCollection([$media]));

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        /** @var ManufacturerLogoStruct $data */
        $data = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $data);
        static::assertSame('media-1', $data->getMediaId());
        static::assertSame('http://localhost', $data->getUrl());
        static::assertTrue($data->getNewTab());

        $media = $data->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media-1', $media->getId());
    }

    public function testEnrichMappedSlotUsesMappedProductResult(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $resolverContextProduct = new SalesChannelProductEntity();
        $resolverContextProduct->setId('product-1');

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            $resolverContextProduct
        );

        $mappedMedia = new MediaEntity();
        $mappedMedia->setId('media-from-result');

        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-1');
        $manufacturer->setMedia($mappedMedia);

        $mappedResult = static::createStub(EntitySearchResult::class);
        $mappedResult->method('getEntities')->willReturn(new ProductManufacturerCollection([$manufacturer]));

        $data = new ElementDataCollection();
        $data->add('mapped_product_manufacturer_slot-1', $mappedResult);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        /** @var ManufacturerLogoStruct $struct */
        $struct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $struct);
        static::assertSame('media-from-result', $struct->getMediaId());
        static::assertNotNull($struct->getManufacturer());
        static::assertSame('manufacturer-1', $struct->getManufacturer()->getId());
    }

    public function testEnrichMappedSlotFallsBackToResolverContextProductWhenMappedProductNotResolvable(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $media = new MediaEntity();
        $media->setId('media-context');

        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer-context');
        $manufacturer->setMedia($media);

        $resolverContextProduct = new SalesChannelProductEntity();
        $resolverContextProduct->setId('product-1');
        $resolverContextProduct->setManufacturer($manufacturer);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            new SalesChannelProductDefinition(),
            $resolverContextProduct
        );

        $invalidEntity = new MediaEntity();
        $invalidEntity->setUniqueIdentifier('invalid-1');
        $invalidMappedResult = static::createStub(EntitySearchResult::class);
        $invalidMappedResult->method('getEntities')->willReturn(new MediaCollection([$invalidEntity]));

        $data = new ElementDataCollection();
        $data->add('mapped_product_manufacturer_slot-1', $invalidMappedResult);

        $resolver = new ManufacturerLogoCmsElementResolver();
        $resolver->enrich($slot, $context, $data);

        /** @var ManufacturerLogoStruct $struct */
        $struct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $struct);
        static::assertSame('media-context', $struct->getMediaId());
        static::assertNotNull($struct->getManufacturer());
        static::assertSame('manufacturer-context', $struct->getManufacturer()->getId());
    }
}
