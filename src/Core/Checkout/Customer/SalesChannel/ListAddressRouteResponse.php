<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CustomerAddressCollection>>
 */
#[Package('checkout')]
class ListAddressRouteResponse extends StoreApiResponse
{
    public function getAddressCollection(): CustomerAddressCollection
    {
        return $this->object->getEntities();
    }
}
