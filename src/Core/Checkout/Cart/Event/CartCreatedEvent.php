<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CartCreatedEvent extends Event implements CartEvent
{
    public function __construct(
        protected Cart $cart
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
