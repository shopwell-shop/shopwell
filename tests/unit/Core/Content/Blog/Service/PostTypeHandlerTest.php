<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Service\PostTypeHandler;
use Shopwell\Core\Framework\Log\Package;

/**
 * The post handler is the contract the storefront route relies on, so the two answers it gives are
 * pinned: the template a reader sees and the schema.org type a crawler reads.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PostTypeHandler::class)]
class PostTypeHandlerTest extends TestCase
{
    public function testItClaimsThePostTypeOnly(): void
    {
        $handler = new PostTypeHandler();

        static::assertTrue($handler->supports(BlogEntity::TYPE_POST));
        static::assertFalse($handler->supports(BlogEntity::TYPE_VIDEO));
        static::assertFalse($handler->supports('podcast'));
        static::assertFalse($handler->supports(''));
    }

    public function testItRendersTheArticleTemplate(): void
    {
        static::assertSame(
            '@Storefront/storefront/page/blog/detail.html.twig',
            (new PostTypeHandler())->getDetailTemplate()
        );
    }

    public function testItDescribesThePageAsABlogPosting(): void
    {
        static::assertSame('BlogPosting', (new PostTypeHandler())->getSchemaType());
    }
}
