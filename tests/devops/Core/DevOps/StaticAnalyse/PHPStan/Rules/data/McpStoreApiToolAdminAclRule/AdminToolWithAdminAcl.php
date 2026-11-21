<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopwell\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(name: 'swag-order-read', description: 'Read an order')]
#[McpToolRequires('order:read')]
class AdminToolWithAdminAcl extends McpToolResponse
{
    public function __invoke(): string
    {
        return $this->requirePrivilege(Context::createDefaultContext(), 'order:read') ?? $this->success([]);
    }
}
