<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopwell\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopwell\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopwell\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ImageSliderTypeDataResolver::class)]
class ImageSliderTypeDataResolverTest extends TestCase
{
    public function testCollectCreatesProductMediaCriteriaIfMappedMediaIsNotResolved(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'),
        ]));

        $context = $this->createEntityResolverContext($this->createProductMediaCollectionWithoutMediaAssociation());

        $criteriaCollection = $resolver->collect($slot, $context);

        static::assertNotNull($criteriaCollection);
        static::assertArrayHasKey(ProductMediaDefinition::class, $criteriaCollection->all());
        static::assertArrayHasKey('product_media_slot-id', $criteriaCollection->all()[ProductMediaDefinition::class]);
    }

    public function testCollectSkipsProductMediaCriteriaIfMappedMediaIsResolved(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'),
        ]));

        $context = $this->createEntityResolverContext($this->createProductMediaCollectionWithMediaAssociation());

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectWithStaticConfigStillCreatesMediaCriteria(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $context = new ResolverContext(static::createStub(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, [['mediaId' => 'media-1']]),
        ]));

        $criteriaCollection = $resolver->collect($slot, $context);

        static::assertNotNull($criteriaCollection);
        static::assertArrayHasKey(MediaDefinition::class, $criteriaCollection->all());
        static::assertArrayHasKey('media_slot-id', $criteriaCollection->all()[MediaDefinition::class]);
    }

    public function testEnrichUsesMappedProductMediaResult(): void
    {
        $resolver = new ImageSliderTypeDataResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $context = $this->createEntityResolverContext(new ProductMediaCollection());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('slot-id');
        $slot->setType('image-slider');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'),
        ]));

        $result = new ElementDataCollection();
        $result->add('product_media_slot-id', new EntitySearchResult(
            'product_media',
            2,
            $this->createProductMediaCollectionWithMediaAssociation(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $resolver->enrich($slot, $context, $result);

        /** @var ImageSliderStruct $data */
        $data = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $data);
        $sliderItems = $data->getSliderItems();
        static::assertIsArray($sliderItems);
        static::assertCount(2, $sliderItems);
    }

    private function createEntityResolverContext(ProductMediaCollection $productMedia): EntityResolverContext
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product-1');
        $product->setMedia($productMedia);

        return new EntityResolverContext(
            static::createStub(SalesChannelContext::class),
            new Request(),
            static::createStub(EntityDefinition::class),
            $product
        );
    }

    private function createProductMediaCollectionWithMediaAssociation(): ProductMediaCollection
    {
        $item = new ProductMediaEntity();
        $item->setId('pm-1');
        $item->setMediaId('media-1');
        $item->setMedia($this->createMediaEntity('media-1'));

        $item2 = new ProductMediaEntity();
        $item2->setId('pm-2');
        $item2->setMediaId('media-2');
        $item2->setMedia($this->createMediaEntity('media-2'));

        return new ProductMediaCollection([$item, $item2]);
    }

    private function createProductMediaCollectionWithoutMediaAssociation(): ProductMediaCollection
    {
        $item = new ProductMediaEntity();
        $item->setId('pm-1');
        $item->setMediaId('media-1');

        return new ProductMediaCollection([$item]);
    }

    private function createMediaEntity(string $id): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId($id);

        return $media;
    }
}
