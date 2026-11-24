<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool('test-tool', 'A tool returning a nullable string')]
class ToolReturningNullableString extends McpToolResponse
{
    public function __invoke(): ?string
    {
        return null;
    }
}
