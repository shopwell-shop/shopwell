<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractProductShippingCostRoute
{
    abstract public function getDecorated(): AbstractProductShippingCostRoute;

    abstract public function shippingCostsByProduct(string $productId, Criteria $criteria, SalesChannelContext $salesChannelContext): ShippingCostRouteResponse;
}
