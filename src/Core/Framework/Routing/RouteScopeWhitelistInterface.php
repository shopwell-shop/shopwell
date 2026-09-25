<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface RouteScopeWhitelistInterface
{
    /**
     * return true, the supplied controller is whitelisted, false if scope matching should be applied
     */
    public function applies(string $controllerClass): bool;
}
