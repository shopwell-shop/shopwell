<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\BlogEntity;
use Shopwell\Core\Content\Blog\Service\VideoTypeHandler;
use Shopwell\Core\Framework\Log\Package;

/**
 * A video page is rendered by the video template and is a VideoObject for a crawler, which is what
 * makes the clip eligible for a rich result. Both answers are pinned here.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(VideoTypeHandler::class)]
class VideoTypeHandlerTest extends TestCase
{
    public function testItClaimsTheVideoTypeOnly(): void
    {
        $handler = new VideoTypeHandler();

        static::assertTrue($handler->supports(BlogEntity::TYPE_VIDEO));
        static::assertFalse($handler->supports(BlogEntity::TYPE_POST));
        static::assertFalse($handler->supports('podcast'));
        static::assertFalse($handler->supports(''));
    }

    public function testItRendersTheVideoTemplate(): void
    {
        static::assertSame(
            '@Storefront/storefront/page/blog/detail-video.html.twig',
            (new VideoTypeHandler())->getDetailTemplate()
        );
    }

    public function testItDescribesThePageAsAVideoObject(): void
    {
        static::assertSame('VideoObject', (new VideoTypeHandler())->getSchemaType());
    }
}
