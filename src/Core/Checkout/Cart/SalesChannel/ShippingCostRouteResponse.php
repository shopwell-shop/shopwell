<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\SalesChannel;

use Shopwell\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopwell\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ShippingCostCollection>
 */
#[Package('checkout')]
class ShippingCostRouteResponse extends StoreApiResponse
{
    public function getShippingCosts(): ShippingCostCollection
    {
        return $this->object;
    }

    public function getShippingCost(string $shippingMethodId): ?CalculatedPrice
    {
        return $this->object->get($shippingMethodId)?->shippingCost;
    }

    public function getDeliveryDate(string $shippingMethodId): ?DeliveryDate
    {
        return $this->object->get($shippingMethodId)?->deliveryDate;
    }

    public function getShippingMethod(string $shippingMethodId): ?ShippingMethodEntity
    {
        return $this->object->get($shippingMethodId)?->shippingMethod;
    }
}
