<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'swag-shipping-info', description: 'Return shipping information')]
#[McpToolRequires('shipping_method:read')]
class DualScopeToolWithRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
