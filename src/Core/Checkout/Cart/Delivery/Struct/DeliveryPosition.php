<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Delivery\Struct;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('checkout')]
class DeliveryPosition extends Struct
{
    public function __construct(
        protected string $identifier,
        protected LineItem $lineItem,
        protected int $quantity,
        protected CalculatedPrice $price,
        protected DeliveryDate $deliveryDate
    ) {
    }

    public function getLineItem(): LineItem
    {
        return $this->lineItem;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return $this->deliveryDate;
    }

    public function getApiAlias(): string
    {
        return 'cart_delivery_position';
    }
}
