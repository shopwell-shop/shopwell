<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Loyalty\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyReturnPoints;
use Shopwell\Core\Checkout\Loyalty\Subscriber\OrderReturnStateChangeLoyaltySubscriber;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * A closed return is the moment the goods really came back, so it is the moment the points the order
 * earned go with them. Everything else a return does - being requested, reshipped, refunded - must
 * leave the balance alone.
 *
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderReturnStateChangeLoyaltySubscriber::class)]
class OrderReturnStateChangeLoyaltySubscriberTest extends TestCase
{
    public function testIgnoresNonLiveVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());
        $returnId = Uuid::randomHex();

        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->never())->method('revokeEarned');

        $this->subscriber($points, [$this->orderReturn($returnId)])
            ->onOrderReturnClosed($this->event(OrderReturnEntity::ENTITY_NAME, $returnId, OrderReturnStates::STATE_CLOSED, $context));
    }

    public function testIgnoresOtherEntities(): void
    {
        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->never())->method('revokeEarned');

        $this->subscriber($points, [])
            ->onOrderReturnClosed($this->event('order', Uuid::randomHex(), OrderReturnStates::STATE_CLOSED));
    }

    public function testIgnoresStatesThatAreNotClosed(): void
    {
        $returnId = Uuid::randomHex();

        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->never())->method('revokeEarned');

        $this->subscriber($points, [$this->orderReturn($returnId)])
            ->onOrderReturnClosed($this->event(OrderReturnEntity::ENTITY_NAME, $returnId, OrderReturnStates::STATE_REFUNDING));
    }

    public function testIgnoresAnUnknownReturn(): void
    {
        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->never())->method('revokeEarned');

        $this->subscriber($points, [])
            ->onOrderReturnClosed($this->event(OrderReturnEntity::ENTITY_NAME, Uuid::randomHex(), OrderReturnStates::STATE_CLOSED));
    }

    public function testTakesBackThePointsWhenTheReturnIsClosed(): void
    {
        $returnId = Uuid::randomHex();
        $orderReturn = $this->orderReturn($returnId);
        $order = $this->order($orderReturn->orderId);

        $points = $this->createMock(LoyaltyReturnPoints::class);
        $points->expects($this->once())
            ->method('revokeEarned')
            ->with($orderReturn, static::isInstanceOf(OrderEntity::class), static::isInstanceOf(Context::class));

        $this->subscriber($points, [$orderReturn], [$order])
            ->onOrderReturnClosed($this->event(OrderReturnEntity::ENTITY_NAME, $returnId, OrderReturnStates::STATE_CLOSED));
    }

    /**
     * @param list<OrderReturnEntity> $orderReturns
     * @param list<OrderEntity> $orders
     */
    private function subscriber(LoyaltyReturnPoints $points, array $orderReturns, array $orders = []): OrderReturnStateChangeLoyaltySubscriber
    {
        return new OrderReturnStateChangeLoyaltySubscriber(
            StaticEntityRepository::of(OrderReturnCollection::class, $orderReturns === [] ? [[]] : [$orderReturns]),
            StaticEntityRepository::of(OrderCollection::class, $orders === [] ? [[]] : [$orders]),
            $points,
        );
    }

    private function event(string $entityName, string $entityId, string $toState, ?Context $context = null): StateMachineTransitionEvent
    {
        $fromState = new StateMachineStateEntity();
        $fromState->setTechnicalName(OrderReturnStates::STATE_REFUNDING);

        $toPlace = new StateMachineStateEntity();
        $toPlace->setTechnicalName($toState);

        return new StateMachineTransitionEvent(
            $entityName,
            $entityId,
            $fromState,
            $toPlace,
            $context ?? Context::createDefaultContext()
        );
    }

    private function orderReturn(string $id): OrderReturnEntity
    {
        $orderReturn = (new OrderReturnEntity())->assign([
            'id' => $id,
            'returnNumber' => 'RT-1',
            'orderId' => Uuid::randomHex(),
            'customerId' => Uuid::randomHex(),
            'currency' => 'CNY',
            'refundAmount' => 15000,
        ]);

        // the entity carries its id as a plain property, so the collection has to be told which key
        // the repository answers - the dal does that for every entity it hydrates
        $orderReturn->setUniqueIdentifier($id);

        return $orderReturn;
    }

    private function order(string $id): OrderEntity
    {
        return (new OrderEntity())->assign(['id' => $id]);
    }
}
