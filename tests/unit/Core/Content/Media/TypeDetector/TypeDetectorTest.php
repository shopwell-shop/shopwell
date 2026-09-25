<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Media\TypeDetector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaType\AudioType;
use Shopwell\Core\Content\Media\MediaType\BinaryType;
use Shopwell\Core\Content\Media\MediaType\DocumentType;
use Shopwell\Core\Content\Media\MediaType\ImageType;
use Shopwell\Core\Content\Media\MediaType\VideoType;
use Shopwell\Core\Content\Media\TypeDetector\AudioTypeDetector;
use Shopwell\Core\Content\Media\TypeDetector\DefaultTypeDetector;
use Shopwell\Core\Content\Media\TypeDetector\DocumentTypeDetector;
use Shopwell\Core\Content\Media\TypeDetector\ImageTypeDetector;
use Shopwell\Core\Content\Media\TypeDetector\TypeDetector;
use Shopwell\Core\Content\Media\TypeDetector\VideoTypeDetector;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TypeDetector::class)]
class TypeDetectorTest extends TestCase
{
    public function testDetectGif(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(1, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
    }

    public function testDetectAnimatedGif(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/animated.gif')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(2, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
        static::assertTrue($type->is(ImageType::ANIMATED));
    }

    public function testDetectWebp(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopwell-logo.vp8x.webp')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(1, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
    }

    public function testDetectAnimatedWebp(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/animated.webp')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(2, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
        static::assertTrue($type->is(ImageType::ANIMATED));
    }

    public function testDetectAvif(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopwell-logo.avif')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(1, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
    }

    public function testDetectAnimatedAvif(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/animated.avif')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(2, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
        static::assertTrue($type->is(ImageType::ANIMATED));
    }

    public function testDetectSvg(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(1, $type->getFlags());
        static::assertTrue($type->is(ImageType::VECTOR_GRAPHIC));
    }

    public function testDetectJpg(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopwell.jpg')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(0, $type->getFlags());
    }

    public function testDetectPng(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopwell-logo.png')
        );

        static::assertInstanceOf(ImageType::class, $type);
        static::assertCount(1, $type->getFlags());
        static::assertTrue($type->is(ImageType::TRANSPARENT));
    }

    public function testDetectDoc(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc')
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectDocx(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx')
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectPdf(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.pdf')
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectAvi(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi')
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMov(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov')
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMp4(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4')
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectWebm(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm')
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectIso(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso')
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectMp3(): void
    {
        $type = $this->getTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3')
        );

        static::assertInstanceOf(AudioType::class, $type);
    }

    private function getTypeDetector(): TypeDetector
    {
        return new TypeDetector([
            new AudioTypeDetector(),
            new VideoTypeDetector(),
            new ImageTypeDetector(),
            new DocumentTypeDetector(),
            new DefaultTypeDetector(),
        ]);
    }

    private function createMediaFile(string $filePath): MediaFile
    {
        static::assertIsString($mimeContentType = mime_content_type($filePath));
        static::assertIsInt($filesize = filesize($filePath));

        return new MediaFile(
            $filePath,
            $mimeContentType,
            pathinfo($filePath, \PATHINFO_EXTENSION),
            $filesize
        );
    }
}
