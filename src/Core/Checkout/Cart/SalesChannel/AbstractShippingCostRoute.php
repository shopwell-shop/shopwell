<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractShippingCostRoute
{
    abstract public function getDecorated(): AbstractShippingCostRoute;

    abstract public function shippingCostsCart(Cart $cart, SalesChannelContext $salesChannelContext): ShippingCostRouteResponse;
}
