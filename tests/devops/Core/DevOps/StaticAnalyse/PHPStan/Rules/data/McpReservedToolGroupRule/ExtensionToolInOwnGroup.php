<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'create_cart', description: 'Create a cart')]
#[McpToolGroup('ucp')]
class ExtensionToolInOwnGroup
{
}
