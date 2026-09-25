<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Page\Cms;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Adapter\Translation\Translator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Storefront\Page\Cms\DefaultMediaResolver;

/**
 * @internal
 */
#[Package('discovery')]
class DefaultMediaResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private DefaultMediaResolver $mediaResolver;

    private Stub&AbstractDefaultMediaResolver $decorated;

    protected function setUp(): void
    {
        $packages = static::getContainer()->get('assets.packages');

        $translator = static::createConfiguredStub(
            Translator::class,
            [
                'trans' => 'foobar',
            ]
        );

        $this->decorated = static::createStub(AbstractDefaultMediaResolver::class);
        $this->mediaResolver = new DefaultMediaResolver($this->decorated, $translator, $packages);
    }

    public function testGetDefaultMediaEntityWithoutValidFileName(): void
    {
        $this->decorated->method('getDefaultCmsMediaEntity')->willReturn(null);
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('this/file/does/not/exists');

        static::assertNull($media);
    }

    public function testGetDefaultMediaEntityWithValidFileName(): void
    {
        $this->decorated->method('getDefaultCmsMediaEntity')->willReturn(new MediaEntity());
        $media = $this->mediaResolver->getDefaultCmsMediaEntity('bundles/storefront/assets/default/cms/shopwell.jpg');

        static::assertInstanceOf(MediaEntity::class, $media);

        // ensure url and translations are set correctly
        static::assertStringContainsString('bundles/storefront/assets/default/cms/shopwell.jpg', $media->getUrl());
        static::assertSame('foobar', $media->getTranslated()['title']);
        static::assertSame('foobar', $media->getTranslated()['alt']);
    }
}
