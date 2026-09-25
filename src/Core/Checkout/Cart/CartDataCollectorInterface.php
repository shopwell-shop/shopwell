<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart;

use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface CartDataCollectorInterface
{
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void;
}
