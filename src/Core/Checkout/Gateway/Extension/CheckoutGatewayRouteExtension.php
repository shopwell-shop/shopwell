<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Gateway\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CheckoutGatewayRouteResponse>
 */
#[Package('checkout')]
final class CheckoutGatewayRouteExtension extends Extension
{
    public const NAME = 'checkout-gateway-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Cart $cart,
        public readonly SalesChannelContext $context,
    ) {
    }
}
