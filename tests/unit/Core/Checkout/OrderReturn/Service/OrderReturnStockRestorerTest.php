<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem\OrderReturnLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStockRestorer;
use Shopwell\Core\Content\Product\Stock\AbstractStockStorage;
use Shopwell\Core\Content\Product\Stock\StockAlteration;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * Booking an item back twice sells it twice, so the guard is the interesting part of this service:
 * only an accepted product position that was not booked before changes the stock, and it does so
 * through the stock storage instead of writing the product.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnStockRestorer::class)]
class OrderReturnStockRestorerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testOnlyAcceptedProductPositionsThatWereNotBookedYetGoBackIntoTheStock(): void
    {
        $order = $this->order([
            'accepted' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'already-restocked' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'not-accepted' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'a-promotion' => LineItem::PROMOTION_LINE_ITEM_TYPE,
        ]);

        $changes = $this->alterations(
            $this->orderReturn([
                $this->returnLineItem('accepted', 2),
                $this->returnLineItem('already-restocked', 1, restocked: true),
                $this->returnLineItem('not-accepted', 1, accepted: false),
                // the order line item of this position is gone, or it never was a product
                $this->returnLineItem('unknown-position', 1),
                $this->returnLineItem('a-promotion', 1),
            ]),
            $order
        );

        static::assertCount(1, $changes);
        static::assertSame('order-line-item-accepted', $changes[0]->lineItemId);
        static::assertSame('product-accepted', $changes[0]->productId);
        // the delta of an alteration is "quantity before minus quantity after", so passing the returned
        // quantity as the negative new quantity adds it to the stock
        static::assertSame(2, $changes[0]->quantityDelta());
    }

    public function testTheBookedPositionsAreMarkedSoARepeatedReceptionDoesNothing(): void
    {
        $repository = new StaticEntityRepository([new OrderReturnLineItemCollection()]);
        $order = $this->order(['a-position' => LineItem::PRODUCT_LINE_ITEM_TYPE]);

        $restocked = $this->restorer($repository, $this->storage())->restock(
            $this->orderReturn([$this->returnLineItem('a-position', 1)]),
            $order,
            $this->context
        );

        static::assertSame(1, $restocked);
        static::assertSame([[['id' => 'order-line-item-a-position', 'restocked' => true]]], $repository->updates);
    }

    public function testAReturnWithoutBookablePositionsDoesNotTouchTheStorage(): void
    {
        $storage = $this->createMock(AbstractStockStorage::class);
        $storage->expects($this->never())->method('alter');

        $repository = new StaticEntityRepository([new OrderReturnLineItemCollection()]);
        $order = $this->order(['a-position' => LineItem::PRODUCT_LINE_ITEM_TYPE]);

        $restocked = $this->restorer($repository, $storage)->restock(
            $this->orderReturn([$this->returnLineItem('a-position', 1, accepted: false)]),
            $order,
            $this->context
        );

        static::assertSame(0, $restocked);
        static::assertSame([], $repository->updates);
    }

    public function testAReturnWithoutLineItemsIsNoError(): void
    {
        $restocked = $this->restorer(
            new StaticEntityRepository([new OrderReturnLineItemCollection()]),
            $this->storage()
        )->restock(new OrderReturnEntity(), $this->order(), $this->context);

        static::assertSame(0, $restocked);
    }

    /**
     * @return list<StockAlteration>
     */
    private function alterations(OrderReturnEntity $orderReturn, OrderEntity $order): array
    {
        /** @var list<StockAlteration> $changes */
        $changes = [];

        $storage = $this->createMock(AbstractStockStorage::class);
        $storage->expects($this->once())
            ->method('alter')
            ->willReturnCallback(static function (array $alterations) use (&$changes): void {
                /** @var list<StockAlteration> $alterations */
                $changes = $alterations;
            });

        $this->restorer(new StaticEntityRepository([new OrderReturnLineItemCollection()]), $storage)
            ->restock($orderReturn, $order, $this->context);

        return $changes;
    }

    /**
     * @param StaticEntityRepository<OrderReturnLineItemCollection> $repository
     */
    private function restorer(StaticEntityRepository $repository, AbstractStockStorage $storage): OrderReturnStockRestorer
    {
        return new OrderReturnStockRestorer($repository, $storage);
    }

    private function storage(): AbstractStockStorage
    {
        return static::createStub(AbstractStockStorage::class);
    }

    /**
     * @param list<OrderReturnLineItemEntity> $lineItems
     */
    private function orderReturn(array $lineItems): OrderReturnEntity
    {
        return (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'lineItems' => $lineItems,
        ]);
    }

    /**
     * @param array<string, string> $lineItems the name of a position => the type of its order line item
     */
    private function order(array $lineItems = []): OrderEntity
    {
        $entities = [];

        foreach ($lineItems as $name => $type) {
            $entities[] = (new OrderLineItemEntity())->assign([
                'id' => 'order-line-item-' . $name,
                'referencedId' => 'product-' . $name,
                'type' => $type,
            ]);
        }

        return (new OrderEntity())->assign(['lineItems' => new OrderLineItemCollection($entities)]);
    }

    private function returnLineItem(
        string $name,
        int $quantity,
        bool $restocked = false,
        bool $accepted = true,
    ): OrderReturnLineItemEntity {
        return (new OrderReturnLineItemEntity())->assign([
            'id' => 'order-line-item-' . $name,
            'quantity' => $quantity,
            'restocked' => $restocked,
            'accepted' => $accepted,
            'orderLineItemId' => 'order-line-item-' . $name,
        ]);
    }
}
