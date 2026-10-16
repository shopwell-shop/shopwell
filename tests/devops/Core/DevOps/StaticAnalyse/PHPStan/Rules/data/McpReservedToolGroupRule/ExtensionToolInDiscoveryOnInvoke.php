<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolGroup;

class ExtensionToolInDiscoveryOnInvoke
{
    #[McpTool(name: 'lookup_catalog', description: 'Look up a product')]
    #[McpToolGroup('discovery')]
    public function __invoke(): string
    {
        return '';
    }
}
