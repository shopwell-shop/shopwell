<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<list<LineItem>>
 */
#[Package('checkout')]
final class CheckoutCartCollectReorderLineItemsExtension extends Extension
{
    public const NAME = 'checkout.cart.collect-reorder-line-items';

    /**
     * @internal
     */
    public function __construct(
        /**
         * @public
         *
         * @description Allows you to access the order that is re-added. The collected line items are built from
         * scratch and carry no persisted payload, so this is where you read your own data to rebuild your line
         * item types
         */
        public readonly OrderEntity $order,

        /**
         * @public
         *
         * @description Allows you to access the cart the collected line items are added to
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
