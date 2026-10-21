<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Subscriber;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnCollection;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnEvents;
use Shopwell\Core\Checkout\OrderReturn\OrderReturnStates;
use Shopwell\Core\Checkout\OrderReturn\Event\OrderReturnCompletedEvent;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnStateHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentRefundFinishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Closes a return once the payment gateway really paid it out.
 *
 * A channel may confirm asynchronously - a bank transfer, a card that is refunded overnight - so the
 * return was left in `refunding` when the payout was handed over. This is the only place that
 * observes the confirmation of the gateway, which keeps the direction of the dependency intact: the
 * payment gateway never knows that returns exist.
 *
 * @internal
 */
#[Package('after-sales')]
class OrderReturnRefundFinishedSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderReturnCollection> $orderReturnRepository
     */
    public function __construct(
        private readonly EntityRepository $orderReturnRepository,
        private readonly OrderReturnStateHandler $stateHandler,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentRefundFinishedEvent::class => 'onRefundFinished',
        ];
    }

    public function onRefundFinished(PaymentRefundFinishedEvent $event): void
    {
        $orderReturn = $this->findByRefundNo($event);

        if (!$orderReturn instanceof OrderReturnEntity) {
            // a refund of a checkout, of an app or of a manual payout - not everything the gateway
            // refunds belongs to a return
            return;
        }

        if ($orderReturn->stateMachineState?->getTechnicalName() !== OrderReturnStates::STATE_REFUNDING) {
            // already closed by a synchronous channel, or never handed to the gateway
            return;
        }

        $this->stateHandler->finish($orderReturn->id, $event->getContext());

        $orderReturn = $this->findByRefundNo($event);

        if ($orderReturn instanceof OrderReturnEntity) {
            $this->eventDispatcher->dispatch(
                new OrderReturnCompletedEvent($orderReturn, $event->getContext()),
                OrderReturnEvents::RETURN_COMPLETED_EVENT
            );
        }
    }

    private function findByRefundNo(PaymentRefundFinishedEvent $event): ?OrderReturnEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentRefundNo', $event->getPaymentRefund()->getRefundNo()));
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('lineItems');
        $criteria->setLimit(1);

        $orderReturn = $this->orderReturnRepository->search($criteria, $event->getContext())->getEntities()->first();

        return $orderReturn instanceof OrderReturnEntity ? $orderReturn : null;
    }
}
