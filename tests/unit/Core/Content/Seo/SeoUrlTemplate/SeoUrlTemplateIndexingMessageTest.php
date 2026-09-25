<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlTemplateIndexingMessage::class)]
class SeoUrlTemplateIndexingMessageTest extends TestCase
{
    public function testItExposesRouteAndEntityName(): void
    {
        $message = new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'category');

        static::assertSame('frontend.navigation.page', $message->routeName);
        static::assertSame('category', $message->entityName);
        static::assertNull($message->offset);
    }

    public function testItExposesIteratorOffsetForChainedMessages(): void
    {
        $message = new SeoUrlTemplateIndexingMessage('frontend.detail.page', 'product', ['offset' => 4711]);

        static::assertSame(['offset' => 4711], $message->offset);
    }
}
