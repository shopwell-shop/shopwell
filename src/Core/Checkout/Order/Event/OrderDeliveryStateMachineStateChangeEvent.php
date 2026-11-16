<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Event;

use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\Aware\OrderDeliveryAware;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('checkout')]
class OrderDeliveryStateMachineStateChangeEvent extends OrderStateMachineStateChangeEvent implements OrderDeliveryAware, ScalarValuesAware
{
    public function __construct(
        string $name,
        OrderEntity $order,
        private readonly OrderDeliveryEntity $orderDelivery,
        Context $context
    ) {
        parent::__construct($name, $order, $context);
    }

    public static function getAvailableData(): EventDataCollection
    {
        return parent::getAvailableData()
            ->add(OrderDeliveryAware::ORDER_DELIVERY, new EntityType(OrderDeliveryDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add(OrderDeliveryAware::ORDER_DELIVERY_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getOrderDelivery(): OrderDeliveryEntity
    {
        return $this->orderDelivery;
    }

    public function getOrderDeliveryId(): string
    {
        return $this->orderDelivery->getId();
    }

    public function getValues(): array
    {
        return [OrderDeliveryAware::ORDER_DELIVERY_ID => $this->getOrderDeliveryId()];
    }
}
