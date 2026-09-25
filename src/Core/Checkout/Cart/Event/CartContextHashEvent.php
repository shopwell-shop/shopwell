<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartContextHashStruct;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellSalesChannelEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CartContextHashEvent extends Event implements ShopwellSalesChannelEvent, CartEvent
{
    public function __construct(
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly Cart $cart,
        protected CartContextHashStruct $hashStruct
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getHashStruct(): CartContextHashStruct
    {
        return $this->hashStruct;
    }

    public function setHashStruct(CartContextHashStruct $hashStruct): void
    {
        $this->hashStruct = $hashStruct;
    }
}
