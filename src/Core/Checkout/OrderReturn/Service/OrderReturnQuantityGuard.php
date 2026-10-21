<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\StateMachineCollection;

/**
 * The gate that keeps a shop from taking back more than was sold.
 *
 * A customer may open one return after the other - a parcel arrives broken, a second one is wrong as
 * well - so the quantity that is still returnable is the ordered quantity minus everything that is
 * already in a return that still holds a claim on the goods. Declined and canceled returns free their
 * quantity again, which is why the states that count are asked from {@see OrderReturnStates} and not
 * spelled out here.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnQuantityGuard
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     * @param EntityRepository<OrderReturnLineItemCollection> $lineItemRepository
     * @param EntityRepository<StateMachineCollection> $stateMachineRepository
     * @param EntityRepository<StateMachineStateCollection> $stateRepository
     */
    public function __construct(
        private readonly EntityRepository $orderReturnRepository,
        private readonly EntityRepository $lineItemRepository,
        private readonly EntityRepository $stateMachineRepository,
        private readonly EntityRepository $stateRepository,
    ) {
    }

    /**
     * The quantity of every line item that is already claimed by a return that is still running.
     *
     * @return array<string, int> line item id => claimed quantity
     */
    public function claimedQuantities(string $orderId, Context $context): array
    {
        $stateIds = $this->blockingStateIds($context);

        if ($stateIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('orderId', $orderId),
            new EqualsAnyFilter('stateId', $stateIds)
        );

        $returnIds = $this->orderReturnRepository->searchIds($criteria, $context)->getIds();

        if ($returnIds === []) {
            return [];
        }

        $lineItemCriteria = new Criteria();
        $lineItemCriteria->addFilter(new EqualsAnyFilter('orderReturnId', $returnIds));

        $claimed = [];

        foreach ($this->lineItemRepository->search($lineItemCriteria, $context)->getEntities() as $lineItem) {
            if (!$lineItem instanceof OrderReturnLineItemEntity) {
                continue;
            }

            $claimed[$lineItem->orderLineItemId] = ($claimed[$lineItem->orderLineItemId] ?? 0) + $lineItem->quantity;
        }

        return $claimed;
    }

    /**
     * @param array<string, int> $requested line item id => quantity the customer wants to send back
     *
     * @throws OrderReturnException when a position does not belong to the order or is over-claimed
     */
    public function assertReturnable(OrderEntity $order, array $requested, Context $context): void
    {
        $claimed = $this->claimedQuantities($order->getId(), $context);

        foreach ($requested as $lineItemId => $quantity) {
            $lineItem = $order->getLineItems()?->get($lineItemId);

            // promotions, discounts and the like are line items as well, but there is nothing to send
            // back for them - a return position has to name a real product
            if (!$lineItem instanceof OrderLineItemEntity || $lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                throw OrderReturnException::lineItemUnknown($lineItemId);
            }

            $available = $lineItem->getQuantity() - ($claimed[$lineItemId] ?? 0);

            if ($quantity < 1 || $quantity > $available) {
                throw OrderReturnException::quantityExceeded($lineItemId, $quantity, max($available, 0));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function blockingStateIds(Context $context): array
    {
        $machineCriteria = new Criteria();
        $machineCriteria->addFilter(new EqualsFilter('technicalName', OrderReturnStates::STATE_MACHINE));
        $machineCriteria->setLimit(1);

        $machine = $this->stateMachineRepository->search($machineCriteria, $context)->getEntities()->first();

        if ($machine === null) {
            return [];
        }

        $stateCriteria = new Criteria();
        $stateCriteria->addFilter(
            new EqualsFilter('stateMachineId', $machine->getId()),
            new EqualsAnyFilter('technicalName', OrderReturnStates::blockingStates())
        );

        return array_values($this->stateRepository->searchIds($stateCriteria, $context)->getIds());
    }
}
