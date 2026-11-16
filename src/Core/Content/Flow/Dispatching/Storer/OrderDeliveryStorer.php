<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Storer;

use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Shared\MailFlow\DataProvider\OrderDeliveryProvider;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderDeliveryStorer extends FlowStorer
{
    public function __construct(private readonly OrderDeliveryProvider $orderDeliveryProvider)
    {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof OrderDeliveryAware || isset($stored[OrderDeliveryAware::ORDER_DELIVERY_ID])) {
            return $stored;
        }

        $stored[OrderDeliveryAware::ORDER_DELIVERY_ID] = $event->getOrderDeliveryId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(OrderDeliveryAware::ORDER_DELIVERY_ID)) {
            return;
        }

        $storable->lazy(
            OrderDeliveryAware::ORDER_DELIVERY,
            $this->lazyLoad(...)
        );
    }

    private function lazyLoad(StorableFlow $storableFlow): ?OrderDeliveryEntity
    {
        $id = $storableFlow->getStore(OrderDeliveryAware::ORDER_DELIVERY_ID);
        if ($id === null) {
            return null;
        }

        return $this->orderDeliveryProvider->getData($id, $storableFlow->getContext());
    }
}
