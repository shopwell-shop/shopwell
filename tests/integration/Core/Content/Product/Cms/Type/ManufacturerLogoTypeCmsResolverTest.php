<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ManufacturerLogoStruct;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopwell\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class ManufacturerLogoTypeCmsResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ManufacturerLogoCmsElementResolver $manufacturerLogoCmsElementResolver;

    protected function setUp(): void
    {
        $this->manufacturerLogoCmsElementResolver = static::getContainer()->get(ManufacturerLogoCmsElementResolver::class);
    }

    public function testType(): void
    {
        static::assertSame('manufacturer-logo', $this->manufacturerLogoCmsElementResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $collection = $this->manufacturerLogoCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');

        $this->manufacturerLogoCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ManufacturerLogoStruct|null $manufacturerLogoStruct */
        $manufacturerLogoStruct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $manufacturerLogoStruct);
        static::assertNull($manufacturerLogoStruct->getManufacturer());
    }

    public function testEnrichEntityResolverContext(): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer_01');
        $product = new SalesChannelProductEntity();
        $product->setId('product_01');
        $product->setManufacturer($manufacturer);
        $resolverContext = new EntityResolverContext(static::createStub(SalesChannelContext::class), new Request(), static::getContainer()->get(SalesChannelProductDefinition::class), $product);
        $result = new ElementDataCollection();

        $media = new MediaEntity();
        $media->setId('media_01');

        $result->add('media_id', new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media_01'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');
        $slot->setFieldConfig($fieldConfig);

        $this->manufacturerLogoCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var ManufacturerLogoStruct|null $manufacturerLogoStruct */
        $manufacturerLogoStruct = $slot->getData();
        static::assertInstanceOf(ManufacturerLogoStruct::class, $manufacturerLogoStruct);
        static::assertNotEmpty($manufacturerLogoStruct->getManufacturer());
        static::assertSame('manufacturer_01', $manufacturerLogoStruct->getManufacturer()->getId());
        static::assertSame('media_01', $manufacturerLogoStruct->getMediaId());
    }

    public function testCollectWithMappedManufacturerWithoutMediaInEntityContext(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product_01');
        $product->setManufacturerId('manufacturer_01');
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer_01');
        $product->setManufacturer($manufacturer);

        $resolverContext = new EntityResolverContext(
            static::createStub(SalesChannelContext::class),
            new Request(),
            static::getContainer()->get(SalesChannelProductDefinition::class),
            $product
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.manufacturer.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('manufacturer-logo');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->manufacturerLogoCmsElementResolver->collect($slot, $resolverContext);
        static::assertNotNull($collection);
        static::assertArrayHasKey(ProductManufacturerDefinition::class, $collection->all());

        $criteria = $collection->all()[ProductManufacturerDefinition::class]['mapped_product_manufacturer_id'];
        static::assertSame(['manufacturer_01'], $criteria->getIds());
        static::assertArrayHasKey('media', $criteria->getAssociations());
    }
}
