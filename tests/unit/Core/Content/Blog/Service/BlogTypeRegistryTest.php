<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\BlogException;
use Shopwell\Core\Content\Blog\Service\BlogTypeHandlerInterface;
use Shopwell\Core\Content\Blog\Service\BlogTypeRegistry;
use Shopwell\Core\Content\Blog\Service\PostTypeHandler;
use Shopwell\Core\Content\Blog\Service\VideoTypeHandler;
use Shopwell\Core\Framework\Log\Package;

/**
 * The registry is the only place that maps a type to a handler, so the two core types and the
 * unknown case are pinned here. The unknown case matters most: an entry with a type nobody claims
 * must fail loudly instead of rendering a blank page.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogTypeRegistry::class)]
class BlogTypeRegistryTest extends TestCase
{
    public function testThePostTypeIsServedByThePostHandler(): void
    {
        $handler = $this->registry()->get(BlogEntity::TYPE_POST);

        static::assertInstanceOf(PostTypeHandler::class, $handler);
    }

    public function testTheVideoTypeIsServedByTheVideoHandler(): void
    {
        $handler = $this->registry()->get(BlogEntity::TYPE_VIDEO);

        static::assertInstanceOf(VideoTypeHandler::class, $handler);
    }

    public function testAnUnknownTypeIsRejected(): void
    {
        try {
            $this->registry()->get('podcast');
            static::fail('An unknown blog type must not be served by any handler.');
        } catch (BlogException $exception) {
            static::assertSame(BlogException::BLOG_TYPE_UNKNOWN, $exception->getErrorCode());
            static::assertSame(['type' => 'podcast'], $exception->getParameters());
        }
    }

    public function testAHandlerAddedByAPluginIsUsed(): void
    {
        $pluginHandler = new class implements BlogTypeHandlerInterface {
            public function supports(string $type): bool
            {
                return $type === 'podcast';
            }

            public function getDetailTemplate(): string
            {
                return '@Plugin/storefront/page/blog/detail-podcast.html.twig';
            }

            public function getSchemaType(): string
            {
                return 'PodcastEpisode';
            }
        };

        $registry = new BlogTypeRegistry([
            new PostTypeHandler(),
            new VideoTypeHandler(),
            $pluginHandler,
        ]);

        static::assertSame($pluginHandler, $registry->get('podcast'));
    }

    public function testTheFirstMatchingHandlerWins(): void
    {
        $first = new class implements BlogTypeHandlerInterface {
            public function supports(string $type): bool
            {
                return true;
            }

            public function getDetailTemplate(): string
            {
                return '@Plugin/first.html.twig';
            }

            public function getSchemaType(): string
            {
                return 'Thing';
            }
        };

        $registry = new BlogTypeRegistry([$first, new PostTypeHandler()]);

        static::assertSame($first, $registry->get(BlogEntity::TYPE_POST));
    }

    private function registry(): BlogTypeRegistry
    {
        return new BlogTypeRegistry([
            new PostTypeHandler(),
            new VideoTypeHandler(),
        ]);
    }
}
