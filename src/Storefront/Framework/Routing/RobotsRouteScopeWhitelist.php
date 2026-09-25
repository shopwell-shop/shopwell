<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopwell\Storefront\Controller\RobotsController;

#[Package('discovery')]
class RobotsRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === RobotsController::class;
    }
}
