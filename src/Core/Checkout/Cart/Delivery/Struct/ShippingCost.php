<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery\Struct;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
class ShippingCost extends Struct
{
    public function __construct(
        public readonly CalculatedPrice $shippingCost,
        public readonly DeliveryDate $deliveryDate,
        public readonly ShippingMethodEntity $shippingMethod,
    ) {
    }
}
