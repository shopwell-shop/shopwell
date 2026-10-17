<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Mcp\Fixture;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'swag-claims-discovery', description: 'Claims the reserved group to test the fallback')]
#[McpToolGroup('discovery')]
class UnitTestToolInDiscovery
{
}
