<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\Event\OrderReturnCompletedEvent;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnEvents;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Checkout\OrderReturn\Subscriber\OrderReturnRefundFinishedSubscriber;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentRefundFinishedEvent;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Order\Refund\PaymentRefundEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The confirmation of the channel is the only moment an asynchronous payout becomes real, so this
 * subscriber is the one place that closes the return for it - and it must not touch a refund that
 * has nothing to do with a return.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnRefundFinishedSubscriber::class)]
class OrderReturnRefundFinishedSubscriberTest extends TestCase
{
    public function testTheSubscriberListensToTheConfirmationOfTheGateway(): void
    {
        static::assertSame(
            [PaymentRefundFinishedEvent::class => 'onRefundFinished'],
            OrderReturnRefundFinishedSubscriber::getSubscribedEvents()
        );
    }

    public function testARefundThatIsNotAReturnIsIgnored(): void
    {
        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->never())->method('finish');

        $this->subscriber(null, $stateHandler)->onRefundFinished($this->event());
    }

    public function testAReturnThatIsNotRefundingIsNotClosed(): void
    {
        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->never())->method('finish');

        $this->subscriber($this->orderReturn(OrderReturnStates::STATE_CLOSED), $stateHandler)
            ->onRefundFinished($this->event());
    }

    public function testTheConfirmedReturnIsClosedAndAnnouncedOnce(): void
    {
        $stateHandler = $this->createMock(OrderReturnStateHandler::class);
        $stateHandler->expects($this->once())->method('finish')->with('return-id');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                static::isInstanceOf(OrderReturnCompletedEvent::class),
                OrderReturnEvents::RETURN_COMPLETED_EVENT
            );

        // the return is read again after the transition, which is when it is closed
        $repository = new StaticEntityRepository([
            new OrderReturnCollection([$this->orderReturn(OrderReturnStates::STATE_REFUNDING)]),
            new OrderReturnCollection([$this->orderReturn(OrderReturnStates::STATE_CLOSED)]),
        ]);

        $subscriber = new OrderReturnRefundFinishedSubscriber($repository, $stateHandler, $eventDispatcher);
        $subscriber->onRefundFinished($this->event());
    }

    private function subscriber(?OrderReturnEntity $found, ?OrderReturnStateHandler $stateHandler): OrderReturnRefundFinishedSubscriber
    {
        return new OrderReturnRefundFinishedSubscriber(
            new StaticEntityRepository([new OrderReturnCollection($found === null ? [] : [$found])]),
            $stateHandler ?? static::createStub(OrderReturnStateHandler::class),
            static::createStub(EventDispatcherInterface::class),
        );
    }

    private function event(): PaymentRefundFinishedEvent
    {
        return new PaymentRefundFinishedEvent(
            (new PaymentRefundEntity())->assign(['id' => 'refund-id', 'refundNo' => 'RF-1']),
            new PaymentOrderEntity(),
            Context::createDefaultContext()
        );
    }

    private function orderReturn(string $state): OrderReturnEntity
    {
        $orderReturn = (new OrderReturnEntity())->assign([
            'id' => 'return-id',
            'returnNumber' => 'RT-1',
            'paymentRefundNo' => 'RF-1',
            'stateMachineState' => (new StateMachineStateEntity())->assign(['technicalName' => $state]),
        ]);
        $orderReturn->setUniqueIdentifier($orderReturn->id);

        return $orderReturn;
    }
}
