<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalesChannelCustomerAddressCollection extends CustomerAddressCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_customer_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelCustomerAddressEntity::class;
    }
}
