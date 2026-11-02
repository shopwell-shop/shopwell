<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Subscriber;

use Shopwell\Core\Checkout\Membership\Purchase\MembershipOrderPurchaseStore;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseAttach;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseSettlement;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Activates a membership once the gateway reports its payment as paid.
 *
 * This is the only place a purchase becomes a membership: the notification of the channel and the
 * reconciliation of open payments both end in {@see PaymentOrderPaidEvent}, so a payment that arrived
 * late is handled exactly like one that arrived on time.
 *
 * A payment of the shop that is not a membership simply carries another attach marker and is ignored -
 * the gateway is shared, the settlement is not.
 *
 * @internal
 */
#[Package('checkout')]
class MembershipPurchaseSettlementSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PurchaseSettlement $settlement,
        private readonly MembershipOrderPurchaseStore $purchases,
        private readonly PurchaseAttach $attach,
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
        $orderId = $this->attach->purchaseIdOf($order->attach);

        if ($orderId === null) {
            return;
        }

        $this->settlement->settle($this->purchases, $orderId, $order->paidAmount, $order->paymentNo, $event->getContext());
    }
}
