<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Subscriber;

use Shopwell\Core\Checkout\Loyalty\Service\LoyaltyReturnPoints;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Takes back the points an order earned once its return is closed.
 *
 * `closed` is the one state a return only reaches when the money went back, so it is the moment the
 * goods really came back as well - the goods were received, or the customer kept them and was paid
 * out anyway. The state machine is the funnel both paths go through (a synchronous channel and one
 * that confirms later), which is why this listens on the transition and not on the
 * `order_return.completed` event: that event is only dispatched by the asynchronous path.
 *
 * A shop that never closes a return does not take points back, and that is intended - the return is
 * still open, so nothing about it is decided.
 *
 * @internal
 */
#[Package('checkout')]
class OrderReturnStateChangeLoyaltySubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderReturnRepository,
        private readonly EntityRepository $orderRepository,
        private readonly LoyaltyReturnPoints $points,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onOrderReturnClosed',
        ];
    }

    public function onOrderReturnClosed(StateMachineTransitionEvent $event): void
    {
        $context = $event->getContext();

        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== OrderReturnEntity::ENTITY_NAME) {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== OrderReturnStates::STATE_CLOSED) {
            return;
        }

        $orderReturn = $this->orderReturnRepository->search(new Criteria([$event->getEntityId()]), $context)->getEntities()->first();

        if (!$orderReturn instanceof OrderReturnEntity) {
            return;
        }

        $order = $this->loadOrder($orderReturn->orderId, $context);

        if (!$order instanceof OrderEntity) {
            return;
        }

        $this->points->revokeEarned($orderReturn, $order, $context);
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        return $order instanceof OrderEntity ? $order : null;
    }
}
