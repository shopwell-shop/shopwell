<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Media\Cms;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('discovery')]
class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private DefaultMediaResolver $mediaResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->mediaResolver = new DefaultMediaResolver($this->publicFilesystem);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $this->publicFilesystem->write('/bundles/core/assets/default/cms/shopwell.jpg', '');
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/core/assets/default/cms/shopwell.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('shopwell', $media->getFileName());
        static::assertSame('image/jpeg', $media->getMimeType());
        static::assertSame('jpg', $media->getFileExtension());
    }
}
