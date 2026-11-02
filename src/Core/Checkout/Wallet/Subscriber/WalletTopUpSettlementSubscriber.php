<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Subscriber;

use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Event\WalletTopUpCreditedEvent;
use Shopwell\Core\Checkout\Wallet\Purchase\WalletTopUpPurchaseStore;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Credits a wallet top-up once the gateway reports its payment as paid, and announces it.
 *
 * This is the only place a top-up becomes money: the notification of the channel and the
 * reconciliation of open payments both end in {@see PaymentOrderPaidEvent}, so a payment that arrived
 * late is handled exactly like one that arrived on time.
 *
 * A payment of the shop that is not a top-up simply carries another attach marker and is ignored -
 * the gateway is shared, the settlement is not.
 *
 * The announcement waits for the settlement to be committed, for the same reason the wallet ledger
 * announces a booking only after its transaction: a listener must never act on a credit that can
 * still be rolled back. It also reads the row again, because the event has to be the whole fact - who
 * paid what - and the payment order only knows the amount the channel collected.
 *
 * @internal
 */
#[Package('checkout')]
class WalletTopUpSettlementSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletTopUpCollection> $topUpRepository
     */
    public function __construct(
        private readonly PurchaseSettlement $settlement,
        private readonly WalletTopUpPurchaseStore $purchases,
        private readonly PurchaseAttach $attach,
        private readonly EntityRepository $topUpRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentOrderPaidEvent::class => 'onPaymentOrderPaid',
        ];
    }

    public function onPaymentOrderPaid(PaymentOrderPaidEvent $event): void
    {
        $order = $event->getPaymentOrder();
        $topUpId = $this->attach->purchaseIdOf($order->attach);

        if ($topUpId === null) {
            return;
        }

        $context = $event->getContext();

        $result = $this->settlement->settle($this->purchases, $topUpId, $order->paidAmount, $order->paymentNo, $context);

        if (!$result->isSettled()) {
            // already credited, or refused: either way nothing new happened that a listener could act on
            return;
        }

        $topUp = $this->topUpRepository->search(new Criteria([$topUpId]), $context)->getEntities()->first();

        if (!$topUp instanceof CustomerWalletTopUpEntity) {
            return;
        }

        $this->eventDispatcher->dispatch(new WalletTopUpCreditedEvent(
            $topUp->getId(),
            $topUp->getCustomerId(),
            $topUp->getCurrency(),
            $topUp->getAmount(),
            $context,
        ));
    }
}
