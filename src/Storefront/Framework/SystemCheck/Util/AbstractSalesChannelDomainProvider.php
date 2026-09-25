<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\SystemCheck\Util;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
abstract class AbstractSalesChannelDomainProvider
{
    abstract public function fetchSalesChannelDomains(): SalesChannelDomainCollection;
}
