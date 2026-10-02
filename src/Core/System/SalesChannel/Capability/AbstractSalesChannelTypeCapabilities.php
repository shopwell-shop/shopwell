<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Capability;

use Shopwell\Core\Framework\Log\Package;

/**
 * Register one implementation per sales channel type id with the tag `shopwell.sales_channel.type_capabilities`.
 * Types without an implementation get the defaults of this class.
 *
 * @internal
 */
#[Package('discovery')]
abstract class AbstractSalesChannelTypeCapabilities
{
    abstract public function getSalesChannelTypeId(): string;

    public function isTransactional(): bool
    {
        return false;
    }
}
