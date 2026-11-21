<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpAdminToolRequiresPrivilegeRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'swag-order-note', description: 'Add a note to an order')]
#[McpToolRequires('order:update')]
class AdminToolWithRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
