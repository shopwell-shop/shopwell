<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CustomerAddressEntity>
 */
#[Package('checkout')]
class UpsertAddressRouteResponse extends StoreApiResponse
{
    public function getAddress(): CustomerAddressEntity
    {
        return $this->object;
    }
}
