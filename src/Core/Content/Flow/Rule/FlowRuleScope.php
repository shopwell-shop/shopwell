<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Rule;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('after-sales')]
class FlowRuleScope extends CartRuleScope
{
    public function __construct(
        private readonly OrderEntity $order,
        Cart $cart,
        SalesChannelContext $context
    ) {
        parent::__construct($cart, $context);
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }
}
