<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequiresNoPrivilege;
use Shopwell\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'swag-order-count', description: 'Count the orders')]
#[McpToolRequiresNoPrivilege(reason: 'Reads no shop data.')]
class AdminToolWithNoPrivilegeCallingRequirePrivilege extends McpToolResponse
{
    public function __invoke(): string
    {
        return $this->requirePrivilege(Context::createDefaultContext(), 'order:read') ?? $this->success([]);
    }
}
