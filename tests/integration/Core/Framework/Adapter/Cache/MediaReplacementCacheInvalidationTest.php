<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Replacing the file of a media item must invalidate every cache tag of a response that renders the media.
 *
 * @internal
 */
#[Package('framework')]
class MediaReplacementCacheInvalidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TEST_IMAGE = __DIR__ . '/../../../Content/Media/fixtures/shopwell-logo.png';

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();

        $this->uploadFile($this->ids->get('media'));
    }

    public function testInvalidatesProductOfProductMedia(): void
    {
        $this->createProduct(
            (new ProductBuilder($this->ids, 'product'))->price(10)->build(),
            mediaId: $this->ids->get('media'),
        );

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildProductTag($this->ids->get('product')), $tags);
    }

    public function testInvalidatesProductOfManufacturerLogo(): void
    {
        $product = (new ProductBuilder($this->ids, 'product'))->price(10)->manufacturer('manufacturer')->build();
        $product['manufacturer']['mediaId'] = $this->ids->get('media');
        $this->createProduct($product);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildManufacturerTag($this->ids->get('manufacturer')), $tags);
    }

    public function testInvalidatesProductListingOfCategoriesOfProductMedia(): void
    {
        $this->createProduct(
            (new ProductBuilder($this->ids, 'product'))->price(10)->category('category')->build(),
            mediaId: $this->ids->get('media'),
        );

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(ProductListingRoute::buildName($this->ids->get('category')), $tags);
    }

    public function testInvalidatesProductStreamOfProductMedia(): void
    {
        $this->createProduct(
            (new ProductBuilder($this->ids, 'product'))->price(10)->build(),
            mediaId: $this->ids->get('media'),
        );

        static::getContainer()->get('product_stream.repository')->create([
            ['id' => $this->ids->get('stream'), 'name' => 'stream'],
        ], $this->context);

        // the mapping is usually written asynchronously by the product stream indexer
        static::getContainer()->get(Connection::class)->insert('product_stream_mapping', [
            'product_id' => Uuid::fromHexToBytes($this->ids->get('product')),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_stream_id' => Uuid::fromHexToBytes($this->ids->get('stream')),
        ]);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(EntityCacheKeyGenerator::buildStreamTag($this->ids->get('stream')), $tags);
    }

    public function testInvalidatesCategoryOfCategoryMedia(): void
    {
        static::getContainer()->get('category.repository')->create([
            ['id' => $this->ids->get('category'), 'name' => 'category', 'mediaId' => $this->ids->get('media')],
        ], $this->context);

        $tags = $this->replaceFileAndCollectInvalidatedTags($this->ids->get('media'));

        static::assertContains(CategoryRoute::buildName($this->ids->get('category')), $tags);
    }

    /**
     * @return list<string>
     */
    private function replaceFileAndCollectInvalidatedTags(string $mediaId): array
    {
        $tags = [];

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            InvalidateCacheEvent::class,
            static function (InvalidateCacheEvent $event) use (&$tags): void {
                $tags = [...$tags, ...$event->getKeys()];
            }
        );

        $this->uploadFile($mediaId);

        return array_values(array_unique($tags));
    }

    private function uploadFile(string $mediaId): void
    {
        static::getContainer()->get('media.repository')->upsert([['id' => $mediaId]], $this->context);

        $filesystem = new Filesystem();
        $tempFile = $filesystem->tempnam(sys_get_temp_dir(), '');
        $filesystem->copy(self::TEST_IMAGE, $tempFile, true);

        try {
            static::getContainer()->get(FileSaver::class)->persistFileToMedia(
                new MediaFile($tempFile, 'image/png', 'png', (int) filesize($tempFile)),
                'media-' . $mediaId,
                $mediaId,
                $this->context
            );
        } finally {
            $filesystem->remove($tempFile);
        }
    }

    /**
     * @param array<string, mixed> $product
     */
    private function createProduct(array $product, ?string $mediaId = null): void
    {
        if ($mediaId !== null) {
            $product['media'] = [['id' => $this->ids->get('product-media'), 'mediaId' => $mediaId]];
            $product['coverId'] = $this->ids->get('product-media');
        }

        static::getContainer()->get('product.repository')->create([$product], $this->context);
    }
}
