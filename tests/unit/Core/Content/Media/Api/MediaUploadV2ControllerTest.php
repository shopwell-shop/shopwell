<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Api\MediaUploadV2Controller;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Content\Media\MediaException;
use Shopwell\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopwell\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopwell\Core\Content\Media\Thumbnail\ExternalThumbnailsParameters;
use Shopwell\Core\Content\Media\Upload\MediaUploadParameters;
use Shopwell\Core\Content\Media\Upload\MediaUploadService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadV2Controller::class)]
class MediaUploadV2ControllerTest extends TestCase
{
    private MediaUploadService&Stub $mediaUploadService;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private MediaUploadV2Controller $controller;

    protected function setUp(): void
    {
        $this->mediaUploadService = static::createStub(MediaUploadService::class);
        $this->mediaRepository = new StaticEntityRepository([]);
        $this->controller = $this->createController();
    }

    public function testUpload(): void
    {
        $mediaId = Uuid::randomHex();
        $request = new Request();
        $context = Context::createDefaultContext();

        $mediaUploadService = $this->createMock(MediaUploadService::class);
        $mediaUploadService
            ->expects($this->once())
            ->method('uploadFromRequest')
            ->with($request, $context, static::isInstanceOf(MediaUploadParameters::class))
            ->willReturn($mediaId);

        $response = $this->createController($mediaUploadService)->upload($request, new MediaUploadParameters(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testUploadUrl(): void
    {
        $mediaId = Uuid::randomHex();
        $url = 'https://example.com/image.jpg';
        $request = new Request([], ['url' => $url]);
        $context = Context::createDefaultContext();

        $mediaUploadService = $this->createMock(MediaUploadService::class);
        $mediaUploadService
            ->expects($this->once())
            ->method('uploadFromURL')
            ->with($url, $context, static::isInstanceOf(MediaUploadParameters::class))
            ->willReturn($mediaId);

        $response = $this->createController($mediaUploadService)->uploadUrl($request, new MediaUploadParameters(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testUploadUrlWithMissingUrl(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(MediaException::invalidUrl(''));

        $this->controller->uploadUrl($request, new MediaUploadParameters(), $context);
    }

    public function testUploadUrlWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => 123]);
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(MediaException::invalidUrl('123'));

        $this->controller->uploadUrl($request, new MediaUploadParameters(), $context);
    }

    public function testExternalLink(): void
    {
        $mediaId = Uuid::randomHex();
        $url = 'https://example.com/image.jpg';
        $request = new Request([], ['url' => $url]);
        $context = Context::createDefaultContext();

        $mediaUploadService = $this->createMock(MediaUploadService::class);
        $mediaUploadService
            ->expects($this->once())
            ->method('linkURL')
            ->with($url, $context, static::isInstanceOf(MediaUploadParameters::class))
            ->willReturn($mediaId);

        $response = $this->createController($mediaUploadService)->externalLink($request, new MediaUploadParameters(), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(['id' => $mediaId], $content);
    }

    public function testExternalLinkWithMissingUrl(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);

        $this->controller->externalLink($request, new MediaUploadParameters(), $context);
    }

    public function testExternalLinkWithNonStringUrl(): void
    {
        $request = new Request([], ['url' => 123]);
        $context = Context::createDefaultContext();

        $this->expectExceptionObject(MediaException::invalidUrl('123'));
        $this->controller->externalLink($request, new MediaUploadParameters(), $context);
    }

    public function testAddExternalThumbnails(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('http://localhost:8000/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $params = new ExternalThumbnailsParameters(new ExternalThumbnailCollection([
            new ExternalThumbnailData('http://localhost:8000/thumb-200.jpg', 200, 200),
            new ExternalThumbnailData('http://localhost:8000/thumb-400.jpg', 400, 400),
        ]));

        $mediaUploadService = $this->createMock(MediaUploadService::class);
        $mediaUploadService
            ->expects($this->once())
            ->method('addExternalThumbnailsToMedia')
            ->with($mediaId, static::callback(static fn ($arg) => $arg instanceof ExternalThumbnailCollection && $arg->count() === 2), $context);

        $response = $this->createController($mediaUploadService)->addExternalThumbnails($mediaId, $params, $context);

        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame($mediaId, $content['mediaId']);
        static::assertSame(2, $content['thumbnailsCreated']);
    }

    public function testAddExternalThumbnailsWithNonExternalMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('media/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::externalMediaRequired($mediaId));

        $this->controller->addExternalThumbnails($mediaId, new ExternalThumbnailsParameters(), $context);
    }

    public function testAddExternalThumbnailsWithNonExistentMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([]));

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->addExternalThumbnails($mediaId, new ExternalThumbnailsParameters(), $context);
    }

    public function testDeleteExternalThumbnails(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('http://localhost:8000/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $mediaUploadService = $this->createMock(MediaUploadService::class);
        $mediaUploadService
            ->expects($this->once())
            ->method('deleteAllExternalThumbnails')
            ->with($mediaId, $context);

        $response = $this->createController($mediaUploadService)->deleteExternalThumbnails($mediaId, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertSame($mediaId, $content['mediaId']);
    }

    public function testDeleteExternalThumbnailsWithNonExternalMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setPath('media/image.jpg');

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::externalMediaRequired($mediaId));

        $this->controller->deleteExternalThumbnails($mediaId, $context);
    }

    public function testDeleteExternalThumbnailsWithNonExistentMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->mediaRepository->addSearch(new MediaCollection([]));

        $this->expectExceptionObject(MediaException::mediaNotFound($mediaId));

        $this->controller->deleteExternalThumbnails($mediaId, $context);
    }

    public function testAddExternalThumbnailsThrowsWhenMediaHasNoPath(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::emptyMediaPath($mediaId));

        $this->controller->addExternalThumbnails($mediaId, new ExternalThumbnailsParameters(), $context);
    }

    public function testDeleteExternalThumbnailsThrowsWhenMediaHasNoPath(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $media = new MediaEntity();
        $media->setId($mediaId);

        $this->mediaRepository->addSearch(new MediaCollection([$media]));

        $this->expectExceptionObject(MediaException::emptyMediaPath($mediaId));

        $this->controller->deleteExternalThumbnails($mediaId, $context);
    }

    private function createController(?MediaUploadService $mediaUploadService = null): MediaUploadV2Controller
    {
        return new MediaUploadV2Controller(
            $mediaUploadService ?? $this->mediaUploadService,
            $this->mediaRepository
        );
    }
}
