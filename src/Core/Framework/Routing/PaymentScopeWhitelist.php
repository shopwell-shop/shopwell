<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing;

use Shopwell\Core\Checkout\Payment\Controller\PaymentController;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class PaymentScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === PaymentController::class;
    }
}
