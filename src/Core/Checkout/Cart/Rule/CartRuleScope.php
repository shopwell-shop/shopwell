<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('fundamentals@after-sales')]
class CartRuleScope extends CheckoutRuleScope
{
    public function __construct(
        protected Cart $cart,
        SalesChannelContext $context
    ) {
        parent::__construct($context);
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
