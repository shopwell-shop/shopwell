<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Rule;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\CheckoutRuleScope;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('fundamentals@after-sales')]
class LineItemScope extends CheckoutRuleScope
{
    public function __construct(
        protected LineItem $lineItem,
        SalesChannelContext $context
    ) {
        parent::__construct($context);
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }
}
