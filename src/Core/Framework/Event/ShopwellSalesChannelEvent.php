<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Event;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
interface ShopwellSalesChannelEvent extends ShopwellEvent
{
    public function getSalesChannelContext(): SalesChannelContext;
}
