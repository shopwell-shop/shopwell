<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AfterLineItemQuantityChangedEvent implements ShopwellSalesChannelEvent, CartEvent
{
    /**
     * @param array<array<string, mixed>> $items
     */
    public function __construct(
        protected Cart $cart,
        protected array $items,
        protected SalesChannelContext $salesChannelContext
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
