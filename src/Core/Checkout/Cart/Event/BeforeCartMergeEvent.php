<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class BeforeCartMergeEvent extends Event implements ShopwellSalesChannelEvent
{
    /**
     * @internal
     */
    public function __construct(
        protected Cart $customerCart,
        protected Cart $guestCart,
        protected LineItemCollection $mergeableLineItems,
        protected SalesChannelContext $context
    ) {
    }

    public function getCustomerCart(): Cart
    {
        return $this->customerCart;
    }

    public function getGuestCart(): Cart
    {
        return $this->guestCart;
    }

    public function getMergeableLineItems(): LineItemCollection
    {
        return $this->mergeableLineItems;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
