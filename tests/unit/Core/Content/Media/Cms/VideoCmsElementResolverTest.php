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
use Shopwell\Core\Content\Cms\SalesChannel\Struct\VideoStruct;
use Shopwell\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopwell\Core\Content\Media\Cms\VideoCmsElementResolver;
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
#[CoversClass(VideoCmsElementResolver::class)]
class VideoCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        static::assertSame('video', $resolver->getType());
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
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

    public function testCollectReturnsNullWithMappedConfig(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsNullWithMappedConfigResolvedToMediaEntity(): void
    {
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectCreatesMediaCriteriaWithMappedStringId(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'),
        ]));

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $definitionData = $collection->all()[MediaDefinition::class] ?? null;
        static::assertIsArray($definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = $definitionData['media_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testEnrichStaticSlotWithMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $data);
        static::assertSame('media-1', $data->getMediaId());

        $media = $data->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media-1', $media->getId());
    }

    public function testEnrichWithDefaultMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'bundles/storefront/assets/default/cms/shopware.mp4'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $defaultMedia = new MediaEntity();
        $defaultMedia->setId('default-1');

        $mediaResolver = static::createStub(AbstractDefaultMediaResolver::class);
        $mediaResolver->method('getDefaultCmsMediaEntity')->willReturn($defaultMedia);

        $resolver = new VideoCmsElementResolver($mediaResolver);
        $resolver->enrich($slot, $context, new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $data);
        static::assertSame($defaultMedia, $data->getMedia());
        static::assertNull($data->getMediaId());
    }

    public function testEnrichWithAriaLabel(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
            new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, 'Video description for accessibility'),
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

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('Video description for accessibility', $videoData->getAriaLabel());
    }

    public function testEnrichMappedStringMediaSetsMediaIdAndMedia(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'),
        ]));

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new MediaCollection([$media]));

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('media-1', $videoData->getMediaId());
        static::assertSame($media, $videoData->getMedia());
    }

    public function testEnrichMappedStringMediaSetsOnlyMediaIdWhenSearchResultMissing(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            static::createStub(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'),
        ]));

        $resolver = new VideoCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, new ElementDataCollection());

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('media-1', $videoData->getMediaId());
        static::assertNull($videoData->getMedia());
    }
}
