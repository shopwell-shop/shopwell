<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'create_cart', description: 'Create a cart')]
#[McpToolRequires('order:create')]
class StoreApiToolWithRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
