<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * The route scope of the generic payment gateway.
 *
 * The gateway is an entry point for external apps and therefore must not run through the
 * customer or the admin authentication. The caller is authenticated by
 * {@see \Shopwell\Core\System\Payment\App\PaymentAppAuthenticator} with the app credentials
 * sent along as request parameters, so the scope itself allows every request below its prefix.
 */
#[Package('framework')]
class PaymentApiRouteScope extends AbstractRouteScope
{
    final public const ID = 'payment-api';
    final public const ALLOWED_PATH = 'payment-api';

    protected array $allowedPaths = [self::ALLOWED_PATH];

    public function isAllowed(Request $request): bool
    {
        // authentication happens in the controllers via the request parameters, see the class doc block
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
