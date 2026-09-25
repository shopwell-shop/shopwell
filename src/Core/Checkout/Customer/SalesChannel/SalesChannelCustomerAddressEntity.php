<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalesChannelCustomerAddressEntity extends CustomerAddressEntity
{
    protected bool $isDefaultBillingAddress = false;

    protected bool $isDefaultShippingAddress = false;

    public function isDefaultBillingAddress(): bool
    {
        return $this->isDefaultBillingAddress;
    }

    public function setIsDefaultBillingAddress(bool $isDefaultBillingAddress): void
    {
        $this->isDefaultBillingAddress = $isDefaultBillingAddress;
    }

    public function isDefaultShippingAddress(): bool
    {
        return $this->isDefaultShippingAddress;
    }

    public function setIsDefaultShippingAddress(bool $isDefaultShippingAddress): void
    {
        $this->isDefaultShippingAddress = $isDefaultShippingAddress;
    }
}
