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
use Shopwell\Core\Checkout\OrderReturn\OrderReturnException;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnQuantityGuard;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\StateMachineCollection;
use Shopwell\Core\System\StateMachine\StateMachineEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * A customer may open one return after the other, so the guard is what keeps a shop from taking back
 * more than it sold. Everything that is already claimed by a running return is subtracted from the
 * ordered quantity, and a position that is no product can not be returned at all.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnQuantityGuard::class)]
class OrderReturnQuantityGuardTest extends TestCase
{
    private Context $context;

    private string $returnId;

    private string $stateId;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->returnId = Uuid::randomHex();
        $this->stateId = Uuid::randomHex();
    }

    public function testAShopWithoutTheStateMachineClaimsNothing(): void
    {
        $guard = $this->guard(new StateMachineCollection());

        static::assertSame([], $guard->claimedQuantities(Uuid::randomHex(), $this->context));
    }

    public function testTheQuantitiesOfRunningReturnsAreAddedUp(): void
    {
        $lineItemId = Uuid::randomHex();
        $guard = $this->guard($this->stateMachines(), [
            $this->returnLineItem($lineItemId, 2),
            $this->returnLineItem($lineItemId, 1),
        ]);

        static::assertSame([$lineItemId => 3], $guard->claimedQuantities(Uuid::randomHex(), $this->context));
    }

    public function testWhatIsStillFreeCanBeReturned(): void
    {
        $lineItemId = Uuid::randomHex();
        $guard = $this->guard($this->stateMachines(), [$this->returnLineItem($lineItemId, 2)]);
        $order = $this->order([$this->productLineItem($lineItemId, 5)]);

        $guard->assertReturnable($order, [$lineItemId => 3], $this->context);

        static::addToAssertionCount(1);
    }

    public function testMoreThanTheFreeQuantityIsRejected(): void
    {
        $lineItemId = Uuid::randomHex();
        $guard = $this->guard($this->stateMachines(), [$this->returnLineItem($lineItemId, 2)]);
        $order = $this->order([$this->productLineItem($lineItemId, 5)]);

        $this->expectExceptionObject(OrderReturnException::quantityExceeded($lineItemId, 4, 3));

        $guard->assertReturnable($order, [$lineItemId => 4], $this->context);
    }

    public function testAnOrderWithoutRunningReturnsIsFullyReturnable(): void
    {
        $lineItemId = Uuid::randomHex();
        $guard = $this->guard($this->stateMachines());
        $order = $this->order([$this->productLineItem($lineItemId, 2)]);

        $guard->assertReturnable($order, [$lineItemId => 2], $this->context);

        static::addToAssertionCount(1);
    }

    public function testAPositionThatIsNoProductIsRejected(): void
    {
        $lineItemId = Uuid::randomHex();
        $guard = $this->guard($this->stateMachines());
        $promotion = $this->productLineItem($lineItemId, 1);
        $promotion->setType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        $order = $this->order([$promotion]);

        $this->expectExceptionObject(OrderReturnException::lineItemUnknown($lineItemId));

        $guard->assertReturnable($order, [$lineItemId => 1], $this->context);
    }

    public function testAPositionThatIsNotInTheOrderIsRejected(): void
    {
        $unknownId = Uuid::randomHex();
        $guard = $this->guard($this->stateMachines());
        $order = $this->order([$this->productLineItem(Uuid::randomHex(), 1)]);

        $this->expectExceptionObject(OrderReturnException::lineItemUnknown($unknownId));

        $guard->assertReturnable($order, [$unknownId => 1], $this->context);
    }

    public function testNothingIsAskedForNothingIsChecked(): void
    {
        $guard = $this->guard($this->stateMachines());

        $guard->assertReturnable($this->order([]), [], $this->context);

        static::addToAssertionCount(1);
    }

    /**
     * @param list<OrderReturnLineItemEntity> $returnLineItems
     */
    private function guard(StateMachineCollection $machines, array $returnLineItems = []): OrderReturnQuantityGuard
    {
        return new OrderReturnQuantityGuard(
            new StaticEntityRepository([[$this->returnId]]),
            new StaticEntityRepository([new OrderReturnLineItemCollection($returnLineItems)]),
            new StaticEntityRepository([$machines]),
            new StaticEntityRepository([[$this->stateId]]),
        );
    }

    private function stateMachines(): StateMachineCollection
    {
        $machine = (new StateMachineEntity())->assign(['id' => Uuid::randomHex()]);
        $machine->setUniqueIdentifier($machine->getId());

        return new StateMachineCollection([$machine]);
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function order(array $lineItems): OrderEntity
    {
        foreach ($lineItems as $lineItem) {
            $lineItem->setUniqueIdentifier($lineItem->getId());
        }

        return (new OrderEntity())->assign([
            'id' => Uuid::randomHex(),
            'lineItems' => new OrderLineItemCollection($lineItems),
        ]);
    }

    private function productLineItem(string $id, int $quantity): OrderLineItemEntity
    {
        return (new OrderLineItemEntity())->assign([
            'id' => $id,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'quantity' => $quantity,
            'label' => 'a product',
            'unitPrice' => 10.0,
        ]);
    }

    private function returnLineItem(string $orderLineItemId, int $quantity): OrderReturnLineItemEntity
    {
        $lineItem = new OrderReturnLineItemEntity();
        $lineItem->id = Uuid::randomHex();
        $lineItem->orderReturnId = $this->returnId;
        $lineItem->orderLineItemId = $orderLineItemId;
        $lineItem->quantity = $quantity;
        $lineItem->refundAmount = 0;
        $lineItem->setUniqueIdentifier($lineItem->id);

        return $lineItem;
    }
}
