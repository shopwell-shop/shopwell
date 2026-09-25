<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class BeforeLineItemQuantityChangedEvent implements ShopwellSalesChannelEvent, CartEvent
{
    public function __construct(
        protected readonly LineItem $lineItem,
        protected readonly Cart $cart,
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly int $beforeUpdateQuantity
    ) {
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getBeforeUpdateQuantity(): int
    {
        return $this->beforeUpdateQuantity;
    }
}
