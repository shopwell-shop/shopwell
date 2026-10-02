<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartOrderRouteResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CartOrderRouteResponse>
 */
#[Package('checkout')]
final class CartOrderRouteExtension extends Extension
{
    public const NAME = 'cart-order-route.order';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly SalesChannelContext $context,
        public readonly RequestDataBag $data,
    ) {
    }
}
