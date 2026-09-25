<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Media\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class MediaThumbnailRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private string $mediaThumbnailSizeId;

    protected function setUp(): void
    {
        $this->mediaThumbnailSizeId = Uuid::randomHex();

        $sizeData = [
            'id' => $this->mediaThumbnailSizeId,
            'width' => 100,
            'height' => 200,
        ];

        static::getContainer()->get('media_thumbnail_size.repository')
            ->create([$sizeData], Context::createDefaultContext());
    }

    #[DataProvider('deleteThumbnailProvider')]
    public function testDeleteThumbnail(bool $private): void
    {
        $service = $private ? 'shopwell.filesystem.private' : 'shopwell.filesystem.public';

        $mediaId = Uuid::randomHex();

        $media = $this->createThumbnailWithMedia($mediaId, $private);

        $thumbnailPath = $this->createThumbnailFile($media, $service);

        $thumbnailIds = static::getContainer()->get('media_thumbnail.repository')
            ->searchIds(new Criteria(), Context::createDefaultContext());

        static::getContainer()->get('media_thumbnail.repository')->delete($thumbnailIds->getPrimaryKeyData(), Context::createDefaultContext());
        $this->runWorker();

        static::assertFalse($this->getFilesystem($service)->has($thumbnailPath));
    }

    public static function deleteThumbnailProvider(): \Generator
    {
        yield 'Test private filesystem' => [true];
        yield 'Test public filesystem' => [true];
    }

    private function createThumbnailWithMedia(string $mediaId, bool $private): MediaEntity
    {
        static::getContainer()->get('media.repository')->create([
            [
                'id' => $mediaId,
                'name' => 'test media',
                'fileExtension' => 'png',
                'mimeType' => 'image/png',
                'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                'private' => $private,
                'thumbnails' => [
                    [
                        'width' => 100,
                        'height' => 200,
                        'highDpi' => false,
                        'mediaThumbnailSizeId' => $this->mediaThumbnailSizeId,
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $media = static::getContainer()->get('media.repository')
            ->search(new Criteria([$mediaId]), Context::createDefaultContext())->getEntities()
            ->get($mediaId);

        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }

    private function createThumbnailFile(MediaEntity $media, string $service): string
    {
        $data = [
            'mediaId' => $media->getId(),
            'width' => 100,
            'height' => 200,
            'path' => 'foo/bar.png',
            'mediaThumbnailSizeId' => $this->mediaThumbnailSizeId,
        ];

        static::getContainer()->get('media_thumbnail.repository')
            ->create([$data], Context::createDefaultContext());

        $fs = $this->getFilesystem($service);

        $fs->write('foo/bar.png', 'foo');

        static::assertTrue($fs->has('foo/bar.png'));

        return 'foo/bar.png';
    }
}
