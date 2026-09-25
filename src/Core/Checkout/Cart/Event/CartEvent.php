<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Event;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
interface CartEvent
{
    public function getCart(): Cart;
}
