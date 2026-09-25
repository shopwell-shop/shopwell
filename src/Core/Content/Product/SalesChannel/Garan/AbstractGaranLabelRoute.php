<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Garan;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractGaranLabelRoute
{
    abstract public function getDecorated(): AbstractGaranLabelRoute;

    abstract public function load(string $productId, SalesChannelContext $context): GaranLabelRouteResponse;
}
