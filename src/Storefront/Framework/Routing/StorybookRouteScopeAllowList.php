<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopwell\Storefront\Controller\StorybookController;

/**
 * @internal
 */
#[Package('discovery')]
final class StorybookRouteScopeAllowList implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === StorybookController::class;
    }
}
