<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolLegacyStringResultRule;

use Shopwell\Core\Framework\Mcp\Result\McpToolResult;
use Shopwell\Core\Framework\Mcp\Tool\McpToolResponse;

abstract class AbstractResultTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return $this->successResult([]);
    }
}
