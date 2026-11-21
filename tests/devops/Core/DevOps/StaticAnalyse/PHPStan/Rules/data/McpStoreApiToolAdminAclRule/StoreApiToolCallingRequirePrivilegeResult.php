<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'get_invoice', description: 'Read an invoice of the customer')]
class StoreApiToolCallingRequirePrivilegeResult extends McpToolResponse
{
    public function __invoke(): mixed
    {
        $context = Context::createDefaultContext();

        return static::requirePrivilegeResult($context, 'order:read');
    }
}
