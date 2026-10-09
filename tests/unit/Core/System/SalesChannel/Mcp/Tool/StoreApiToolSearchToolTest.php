<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Shopwell\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopwell\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiToolSearchTool::class)]
#[CoversClass(AbstractToolSearchTool::class)]
class StoreApiToolSearchToolTest extends TestCase
{
    public function testSearchReturnsStoreApiToolDefinitions(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopwell-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('product'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('shopwell-store-api-product-search', $data['data'][0]['tool']['name']);
    }

    public function testResultCarriesToolsetEnableUsageHint(): void
    {
        $registry = new Registry();
        $registry->registerTool(self::tool('shopwell-store-api-product-search', 'Search products'), 'Acme\\ProductSearchTool');

        $tool = new StoreApiToolSearchTool($registry, new ToolSearch());

        $data = json_decode($tool('product'), true, 512, \JSON_THROW_ON_ERROR);

        // Store API now uses progressive disclosure, so tool-search nudges toward the enable path.
        static::assertArrayHasKey('usage', $data['_meta']);
        static::assertStringContainsString('shopwell-toolset-enable', $data['_meta']['usage']);
    }

    private static function tool(string $name, string $description): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: $description,
            annotations: null,
        );
    }
}
