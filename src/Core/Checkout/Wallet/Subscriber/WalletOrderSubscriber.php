<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Subscriber;

use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Checkout\Wallet\Cart\WalletCartProcessor;
use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class WalletOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly WalletService $walletService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onOrderStateChange',
        ];
    }

    public function onOrderStateChange(StateMachineTransitionEvent $event): void
    {
        $context = $event->getContext();

        if ($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        if ($event->getEntityName() !== 'order') {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== OrderStates::STATE_COMPLETED) {
            return;
        }

        $order = $this->loadOrder($event->getEntityId(), $context);
        if (!$order instanceof OrderEntity) {
            return;
        }

        $customerId = $order->getOrderCustomer()?->getCustomerId();
        if ($customerId === null) {
            return;
        }

        $amount = $this->sumWalletPayment($order);

        if ($amount <= 0.0) {
            return;
        }

        $currencyId = $order->getCurrencyId();
        $balance = $this->walletService->getBalance($customerId, $currencyId, $context);

        // the balance may have changed while the order was open, so the debit is capped to what is still available
        $amount = min($amount, $balance);

        if ($amount <= 0.0) {
            return;
        }

        $this->walletService->consume($customerId, $currencyId, $amount, $order->getId(), $context);
    }

    /**
     * Sums the amount the customer paid through the wallet, carried in the payload of the
     * `wallet-payment` credit line item.
     */
    private function sumWalletPayment(OrderEntity $order): float
    {
        $lineItems = $order->getLineItems();

        if ($lineItems === null) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::CREDIT_LINE_ITEM_TYPE || $lineItem->getIdentifier() !== WalletCartProcessor::LINE_ITEM_ID) {
                continue;
            }

            $amount = $lineItem->getPayloadValue('amount');

            if (is_numeric($amount)) {
                $total += (float) $amount;
            }
        }

        return $total;
    }

    private function loadOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();

        return $order instanceof OrderEntity ? $order : null;
    }
}
