<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolResponseTraitRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool('test-tool', 'A valid tool extending McpToolResponse')]
class ToolWithTrait extends McpToolResponse
{
    public function __invoke(): string
    {
        return $this->success([]);
    }
}
