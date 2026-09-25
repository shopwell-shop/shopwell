<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart;

use Shopwell\Core\Checkout\Cart\Event\CartCreatedEvent;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?string $source = null,
    ) {
    }

    public function createNew(string $token): Cart
    {
        $cart = new Cart($token);

        if ($this->source) {
            $cart->setSource($this->source);
        }

        $this->eventDispatcher->dispatch(new CartCreatedEvent($cart));

        return $cart;
    }
}
