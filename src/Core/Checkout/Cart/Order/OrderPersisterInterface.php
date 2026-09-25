<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Order;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface OrderPersisterInterface
{
    public function persist(Cart $cart, SalesChannelContext $context): string;
}
