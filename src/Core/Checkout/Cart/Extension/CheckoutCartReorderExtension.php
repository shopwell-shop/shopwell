<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<CartResponse>
 */
#[Package('checkout')]
final class CheckoutCartReorderExtension extends Extension
{
    public const NAME = 'checkout.cart.reorder';

    /**
     * @internal
     */
    public function __construct(
        /**
         * @public
         *
         * @description Allows you to access the id of the order that is re-added
         */
        public readonly string $orderId,

        /**
         * @public
         *
         * @description Allows you to access the current request
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description Allows you to access the cart the products are added to
         */
        public readonly Cart $cart,

        /**
         * @public
         *
         * @description Allows you to access the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context
    ) {
    }
}
