<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RouteScopeWhitelistInterface;

/**
 * @internal
 */
#[Package('framework')]
class McpOAuthMetadataRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === McpOAuthMetadataController::class;
    }
}
