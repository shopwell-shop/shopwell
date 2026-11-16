<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Scope for rules that are evaluated against a single cart delivery instead of the sales channel context
 *
 * @final
 */
#[Package('fundamentals@after-sales')]
class DeliveryRuleScope extends CartRuleScope
{
    public function __construct(
        protected Delivery $delivery,
        Cart $cart,
        SalesChannelContext $context
    ) {
        parent::__construct($cart, $context);
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }
}
