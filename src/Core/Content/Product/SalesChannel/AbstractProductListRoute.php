<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is a general route to get products of the sales channel
 */
#[Package('inventory')]
abstract class AbstractProductListRoute
{
    abstract public function getDecorated(): AbstractProductListRoute;

    abstract public function load(Criteria $criteria, SalesChannelContext $context): ProductListResponse;
}
