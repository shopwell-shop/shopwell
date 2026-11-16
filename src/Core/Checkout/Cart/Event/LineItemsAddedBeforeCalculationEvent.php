<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched once per add to the cart, after all its line items are in the cart and before the cart is calculated.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
final class LineItemsAddedBeforeCalculationEvent implements ShopwellSalesChannelEvent, CartEvent
{
    /**
     * @param array<LineItem> $lineItems
     */
    public function __construct(
        private readonly array $lineItems,
        private readonly Cart $cart,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    /**
     * @return array<LineItem>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
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
}
