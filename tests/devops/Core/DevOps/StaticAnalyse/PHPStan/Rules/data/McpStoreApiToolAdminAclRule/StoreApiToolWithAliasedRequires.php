<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Mcp\Capability\Attribute\McpTool;
use Shopwell\Core\Framework\Mcp\Attribute\McpToolRequires as Requires;

#[McpTool(name: 'add_to_cart', description: 'Add a product to the cart')]
#[Requires('order:create')]
class StoreApiToolWithAliasedRequires
{
    public function __invoke(): string
    {
        return '';
    }
}
