<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order;

use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<OrderEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCurrencyIds(): array
    {
        return $this->fmap(static fn (OrderEntity $order) => $order->getCurrencyId());
    }

    public function filterByCurrencyId(string $id): self
    {
        return $this->filter(static fn (OrderEntity $order) => $order->getCurrencyId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getSalesChannelIs(): array
    {
        return $this->fmap(static fn (OrderEntity $order) => $order->getSalesChannelId());
    }

    public function filterBySalesChannelId(string $id): self
    {
        return $this->filter(static fn (OrderEntity $order) => $order->getSalesChannelId() === $id);
    }

    public function getOrderCustomers(): OrderCustomerCollection
    {
        return new OrderCustomerCollection(
            $this->fmap(static fn (OrderEntity $order) => $order->getOrderCustomer())
        );
    }

    public function getCurrencies(): CurrencyCollection
    {
        return new CurrencyCollection(
            $this->fmap(static fn (OrderEntity $order) => $order->getCurrency())
        );
    }

    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->fmap(static fn (OrderEntity $order) => $order->getSalesChannel())
        );
    }

    public function getBillingAddress(): OrderAddressCollection
    {
        return new OrderAddressCollection(
            $this->flatMap(static fn (OrderEntity $order) => $order->getAddresses())
        );
    }

    public function getApiAlias(): string
    {
        return 'order_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrderEntity::class;
    }
}
