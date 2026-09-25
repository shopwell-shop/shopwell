<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopwell\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopwell\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfig;
use Shopwell\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopwell\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopwell\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ImageCmsElementResolver::class)]
class ImageCmsElementResolverTest extends TestCase
{
    public function testCollectReturnsNullWithMappedConfigAndResolverContextWithoutEntity(): void
    {
        $resolver = new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroImage'),
        ]));

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsNullWithMappedConfigResolvedToMediaEntity(): void
    {
        $resolver = new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $media = new MediaEntity();
        $media->setId('media-1');

        $productMedia = new ProductMediaEntity();
        $productMedia->setMedia($media);

        $product = new ProductEntity();
        $product->setCover($productMedia);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'cover.media'),
        ]));

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectCreatesMediaCriteriaWithMappedStringId(): void
    {
        $resolver = new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $product = new ProductEntity();
        $product->setCustomFields(['heroImage' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroImage'),
        ]));

        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $definitionData = $collection->all()[MediaDefinition::class] ?? null;
        static::assertIsArray($definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = $definitionData['media_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testEnrichMappedStringMediaSetsMediaIdAndMedia(): void
    {
        $resolver = new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $product = new ProductEntity();
        $product->setCustomFields(['heroImage' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroImage'),
        ]));

        $media = new MediaEntity();
        $media->setId('media-1');

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new MediaCollection([$media]));

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $searchResult);

        $resolver->enrich($slot, $context, $data);

        $imageData = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageData);
        static::assertSame('media-1', $imageData->getMediaId());
        static::assertSame($media, $imageData->getMedia());
    }

    public function testEnrichMappedStringMediaSetsOnlyMediaIdWhenSearchResultMissing(): void
    {
        $resolver = new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));

        $product = new ProductEntity();
        $product->setCustomFields(['heroImage' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroImage'),
        ]));

        $resolver->enrich($slot, $context, new ElementDataCollection());

        $imageData = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageData);
        static::assertSame('media-1', $imageData->getMediaId());
        static::assertNull($imageData->getMedia());
    }
}
