<?php declare(strict_types=1);

namespace Shopwell\Storefront\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('checkout')]
class StorefrontCartGatewayResult
{
    public function __construct(
        public readonly Cart $cart,
        public readonly CheckoutGatewayRouteResponse $gatewayResponse,
    ) {
    }
}
