<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Extension;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Event\CartEvent;
use Shopwell\Core\Checkout\Cart\Order\OrderPlaceResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<OrderPlaceResult>
 */
#[Package('checkout')]
final class CheckoutPlaceOrderExtension extends Extension implements ShopwellSalesChannelEvent, CartEvent
{
    public const NAME = 'checkout.place-order';

    /**
     * @internal shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The cart is already calculated and can be processed to place the order
         */
        public readonly Cart $cart,
        /**
         * @public
         *
         * @description Contains the current customer session parameters
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Contains additional request parameters like customer comments etc.
         */
        public readonly RequestDataBag $data
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
