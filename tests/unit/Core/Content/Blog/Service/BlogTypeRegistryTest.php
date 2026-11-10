<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogTypeRegistry::class)]
class BlogTypeRegistryTest extends TestCase
{
    public function testBuiltInTypesExposeTheirStorefrontDefaults(): void
    {
        $registry = new BlogTypeRegistry();

        static::assertSame(['post', 'video'], $registry->getTypes());
        static::assertSame('@Storefront/storefront/page/blog/detail.html.twig', $registry->getDetailTemplate('post'));
        static::assertSame('@Storefront/storefront/page/blog/detail-video.html.twig', $registry->getDetailTemplate('video'));
        static::assertSame('BlogPosting', $registry->getSchemaType('post'));
        static::assertSame('VideoObject', $registry->getSchemaType('video'));
    }

    public function testExtensionsCanRegisterAdditionalTypesExactlyOnce(): void
    {
        $registry = new BlogTypeRegistry();
        $registry->addType('podcast', '@Podcast/detail.html.twig', 'PodcastEpisode');
        $registry->addType('podcast');

        static::assertTrue($registry->hasType('podcast'));
        static::assertFalse($registry->hasType('PODCAST'));
        static::assertSame(['post', 'video', 'podcast'], $registry->getTypes());
        static::assertSame('@Podcast/detail.html.twig', $registry->getDetailTemplate('podcast'));
        static::assertSame('PodcastEpisode', $registry->getSchemaType('podcast'));
    }

    public function testNumericStringIdentitiesRemainStrings(): void
    {
        $registry = new BlogTypeRegistry();
        $registry->addType('42');

        static::assertSame(['post', 'video', '42'], $registry->getTypes());
    }

    public function testNewTypesCanReuseTheArticlePresentation(): void
    {
        $registry = new BlogTypeRegistry();
        $registry->addType('guide');

        static::assertSame(BlogTypeRegistry::DEFAULT_TEMPLATE, $registry->getDetailTemplate('guide'));
        static::assertSame('BlogPosting', $registry->getSchemaType('guide'));
    }

    public function testAnUnavailableExtensionTypeFallsBackToTheArticlePresentation(): void
    {
        $registry = new BlogTypeRegistry();

        static::assertFalse($registry->hasType('podcast'));
        static::assertSame(BlogTypeRegistry::DEFAULT_TEMPLATE, $registry->getDetailTemplate('podcast'));
        static::assertSame('BlogPosting', $registry->getSchemaType('podcast'));
    }
}
