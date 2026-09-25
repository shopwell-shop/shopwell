<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolGroup;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolGroup::class)]
class McpToolGroupTest extends TestCase
{
    public function testStoresGroupName(): void
    {
        $attribute = new McpToolGroup('catalogue');

        static::assertSame('catalogue', $attribute->group);
    }
}
