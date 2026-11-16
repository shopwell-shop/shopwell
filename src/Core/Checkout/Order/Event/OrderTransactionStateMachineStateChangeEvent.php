<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Event;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
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
class OrderTransactionStateMachineStateChangeEvent extends OrderStateMachineStateChangeEvent implements OrderTransactionAware, ScalarValuesAware
{
    public function __construct(
        string $name,
        OrderEntity $order,
        private readonly OrderTransactionEntity $orderTransaction,
        Context $context
    ) {
        parent::__construct($name, $order, $context);
    }

    public static function getAvailableData(): EventDataCollection
    {
        return parent::getAvailableData()
            ->add(OrderTransactionAware::ORDER_TRANSACTION, new EntityType(OrderTransactionDefinition::class), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true])
            ->add(OrderTransactionAware::ORDER_TRANSACTION_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransaction->getId();
    }

    public function getValues(): array
    {
        return [OrderTransactionAware::ORDER_TRANSACTION_ID => $this->getOrderTransactionId()];
    }
}
