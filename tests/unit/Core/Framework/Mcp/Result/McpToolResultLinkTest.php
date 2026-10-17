<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Result\McpToolResultLink;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultLink::class)]
class McpToolResultLinkTest extends TestCase
{
    public function testCarriesEverythingAResourceLinkNeeds(): void
    {
        $link = new McpToolResultLink('shopwell://tool-result/abc', 'tool-result', 'Too large to return inline.', 'application/json', 123456);

        static::assertSame('shopwell://tool-result/abc', $link->uri);
        static::assertSame('tool-result', $link->name);
        static::assertSame('Too large to return inline.', $link->description);
        static::assertSame('application/json', $link->mimeType);
        static::assertSame(123456, $link->size);
    }

    public function testOnlyTheUriAndNameAreRequired(): void
    {
        $link = new McpToolResultLink('shopwell://tool-result/abc', 'tool-result');

        static::assertNull($link->description);
        static::assertNull($link->mimeType);
        static::assertNull($link->size);
    }
}
