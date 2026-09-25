<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery\Struct;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
class Delivery extends Struct
{
    public function __construct(
        protected DeliveryPositionCollection $positions,
        protected DeliveryDate $deliveryDate,
        protected ShippingMethodEntity $shippingMethod,
        protected ShippingLocation $location,
        protected CalculatedPrice $shippingCosts
    ) {
    }

    public function getPositions(): DeliveryPositionCollection
    {
        return $this->positions;
    }

    public function getLocation(): ShippingLocation
    {
        return $this->location;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getShippingMethod(): ShippingMethodEntity
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodEntity $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getShippingCosts(): CalculatedPrice
    {
        return $this->shippingCosts;
    }

    public function setShippingCosts(CalculatedPrice $shippingCosts): void
    {
        $this->shippingCosts = $shippingCosts;
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery';
    }
}
