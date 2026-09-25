<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Api\MediaUploadController;
use Shopwell\Core\Content\Media\File\FileNameProvider;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaDefinition;
use Shopwell\Core\Content\Media\MediaException;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaUploadController::class)]
class MediaUploadControllerTest extends TestCase
{
    public static bool $simulateFailedTempnam = false;

    private FileSaver&Stub $fileSaver;

    private MediaService&Stub $mediaService;

    private FileNameProvider&Stub $fileNameProvider;

    private ResponseFactoryInterface&Stub $responseFactory;

    protected function setUp(): void
    {
        $this->fileSaver = static::createStub(FileSaver::class);
        $this->mediaService = static::createStub(MediaService::class);
        $this->fileNameProvider = static::createStub(FileNameProvider::class);
        $this->responseFactory = static::createStub(ResponseFactoryInterface::class);
    }

    protected function tearDown(): void
    {
        self::$simulateFailedTempnam = false;
    }

    public function testRemoveNonPrintingCharactersInFileNameBeforeUpload(): void
    {
        $invalidFileName = 'file­name.png';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $request = new Request(['fileName' => $invalidFileName]);

        $uploadFile = new MediaFile(
            '/tmp/foo/bar/baz',
            'image/png',
            'png',
            1000,
            Uuid::randomHex()
        );

        $mediaService = $this->createMock(MediaService::class);
        $mediaService->expects($this->once())
            ->method('fetchFile')
            ->willReturn($uploadFile);

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver->expects($this->once())
            ->method('persistFileToMedia')
            ->with($uploadFile, 'filename.png', $mediaId, $context);

        $mediaUploadController = new MediaUploadController(
            $mediaService,
            $fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $mediaUploadController->upload($request, $mediaId, $context, $this->responseFactory);
    }

    public function testRemoveNonPrintingCharactersInFileNameBeforeRename(): void
    {
        $invalidFileName = 'file­name.png';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $request = new Request([], ['fileName' => $invalidFileName]);

        $fileSaver = $this->createMock(FileSaver::class);
        $fileSaver->expects($this->once())
            ->method('renameMedia')
            ->with($mediaId, 'filename.png', $context);

        $mediaUploadController = new MediaUploadController(
            $this->mediaService,
            $fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $mediaUploadController->renameMediaFile($request, $mediaId, $context, $this->responseFactory);
    }

    public function testRemoveNonPrintingCharactersInFileNameBeforeProvideName(): void
    {
        $invalidFileName = 'file­name.png';
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $request = new Request([
            'fileName' => $invalidFileName,
            'extension' => 'jpg',
            'mediaId' => $mediaId,
        ]);

        $fileNameProvider = $this->createMock(FileNameProvider::class);
        $fileNameProvider->expects($this->once())
            ->method('provide')
            ->with('filename.png', 'jpg', $mediaId, $context);

        $mediaUploadController = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $mediaUploadController->provideName($request, $context);
    }

    public function testRenameThrowsWhenEmptyFileName(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $request = new Request([], ['fileName' => '']);

        $this->expectExceptionObject(MediaException::emptyMediaFilename());

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->renameMediaFile($request, $mediaId, $context, $this->responseFactory);
    }

    public function testProvideNameThrowsWhenEmptyFileName(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request(['fileName' => '', 'extension' => 'jpg']);

        $this->expectExceptionObject(MediaException::emptyMediaFilename());

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->provideName($request, $context);
    }

    public function testProvideNameThrowsWhenMissingExtension(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request(['fileName' => 'test', 'extension' => '']);

        $this->expectExceptionObject(MediaException::missingFileExtension());

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->provideName($request, $context);
    }

    public function testUploadThrowsWhenTempFileCannotBeCreated(): void
    {
        self::$simulateFailedTempnam = true;

        $this->expectExceptionObject(MediaException::cannotCreateTempFile());

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->upload(new Request(), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testUploadThrowsOnIllegalFileName(): void
    {
        $this->expectExceptionObject(MediaException::illegalFileName("\xFF\xFE", 'Path encoding is invalid'));

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->upload(new Request(['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testRenameThrowsOnIllegalFileName(): void
    {
        $this->expectExceptionObject(MediaException::illegalFileName("\xFF\xFE", 'Path encoding is invalid'));

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->renameMediaFile(new Request([], ['fileName' => "\xFF\xFE"]), Uuid::randomHex(), Context::createDefaultContext(), $this->responseFactory);
    }

    public function testProvideNameThrowsOnIllegalFileName(): void
    {
        $this->expectExceptionObject(MediaException::illegalFileName("\xFF\xFE", 'Path encoding is invalid'));

        $controller = new MediaUploadController(
            $this->mediaService,
            $this->fileSaver,
            $this->fileNameProvider,
            new MediaDefinition(),
            new EventDispatcher()
        );

        $controller->provideName(new Request(['fileName' => "\xFF\xFE", 'extension' => 'png']), Context::createDefaultContext());
    }
}

namespace Shopwell\Core\Content\Media\Api;

use Shopwell\Tests\Unit\Core\Content\Media\Api\MediaUploadControllerTest;

function tempnam(string $dir, string $prefix): string|false
{
    if (MediaUploadControllerTest::$simulateFailedTempnam) {
        return false;
    }

    return \tempnam($dir, $prefix);
}
