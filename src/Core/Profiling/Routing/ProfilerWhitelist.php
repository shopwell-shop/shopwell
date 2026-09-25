<?php declare(strict_types=1);

namespace Shopwell\Core\Profiling\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopwell\Core\Profiling\Controller\ProfilerController;

#[Package('framework')]
class ProfilerWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === ProfilerController::class;
    }
}
