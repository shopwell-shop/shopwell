<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderConvertedEvent extends NestedEvent
{
    private Cart $convertedCart;

    public function __construct(
        private readonly OrderEntity $order,
        private readonly Cart $cart,
        private readonly Context $context,
    ) {
        $this->convertedCart = clone $cart;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getConvertedCart(): Cart
    {
        return $this->convertedCart;
    }

    public function setConvertedCart(Cart $convertedCart): void
    {
        $this->convertedCart = $convertedCart;
    }
}
