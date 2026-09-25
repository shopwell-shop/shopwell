<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Shipping\SalesChannel;

use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ShippingMethodCollection>>
 */
#[Package('checkout')]
class ShippingMethodRouteResponse extends StoreApiResponse
{
    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->object->getEntities();
    }
}
