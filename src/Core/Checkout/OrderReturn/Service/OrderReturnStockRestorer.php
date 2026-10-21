<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Content\Product\Stock\AbstractStockStorage;
use Shopwell\Core\Content\Product\Stock\StockAlteration;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * Books the goods of a return back into the stock.
 *
 * Only positions the shop accepted go back into the stock: a parcel may come back with one item
 * unusable and another one fine, and booking a broken item in would sell it twice. `restocked`
 * remembers what was already booked, so a repeated reception - a double click, a retried message -
 * never doubles the stock.
 *
 * The stock is altered through the stock storage and not by writing the product, so a plugin that
 * replaces the storage (a warehouse system, a dropshipper) is respected.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnStockRestorer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderReturnLineItemCollection> $lineItemRepository
     */
    public function __construct(
        private readonly EntityRepository $lineItemRepository,
        private readonly AbstractStockStorage $stockStorage,
    ) {
    }

    /**
     * @return int the number of positions that were booked back
     */
    public function restock(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        $changes = [];
        $restockedIds = [];

        foreach ($orderReturn->lineItems ?? [] as $returnLineItem) {
            if (!$returnLineItem instanceof OrderReturnLineItemEntity) {
                continue;
            }

            if ($returnLineItem->restocked || !$returnLineItem->accepted) {
                continue;
            }

            $orderLineItem = $order->getLineItems()?->get($returnLineItem->orderLineItemId);

            if (!$orderLineItem instanceof OrderLineItemEntity) {
                continue;
            }

            $productId = $orderLineItem->getReferencedId();

            if ($productId === null || $orderLineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            // the delta of an alteration is "quantity before minus quantity after", so passing the
            // returned quantity as the negative new quantity adds it to the stock
            $changes[] = new StockAlteration(
                $returnLineItem->id,
                $productId,
                0,
                -$returnLineItem->quantity
            );

            $restockedIds[] = $returnLineItem->id;
        }

        if ($changes === []) {
            return 0;
        }

        $this->stockStorage->alter($changes, $context);

        $this->lineItemRepository->update(
            array_map(static fn (string $id): array => ['id' => $id, 'restocked' => true], $restockedIds),
            $context
        );

        return \count($restockedIds);
    }
}
