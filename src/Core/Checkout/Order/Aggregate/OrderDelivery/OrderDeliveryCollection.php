<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrderDeliveryEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderDeliveryCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getOrderIds(): array
    {
        return $this->fmap(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getOrderId());
    }

    public function filterByOrderId(string $id): self
    {
        return $this->filter(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getOrderId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getShippingAddressIds(): array
    {
        return $this->fmap(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingOrderAddressId());
    }

    public function filterByShippingAddressId(string $id): self
    {
        return $this->filter(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingOrderAddressId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getShippingMethodIds(): array
    {
        return $this->fmap(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingMethodId());
    }

    public function filterByShippingMethodId(string $id): self
    {
        return $this->filter(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingMethodId() === $id);
    }

    public function getShippingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->fmap(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingOrderAddress())
        );
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return new ShippingMethodCollection(
            $this->fmap(static fn (OrderDeliveryEntity $orderDelivery) => $orderDelivery->getShippingMethod())
        );
    }

    public function getApiAlias(): string
    {
        return 'order_delivery_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderDeliveryEntity::class;
    }
}
