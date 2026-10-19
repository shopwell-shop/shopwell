<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Subscriber;

use Shopwell\Core\Checkout\Loyalty\Service\MembershipService;
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
class MembershipOrderSettlementSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MembershipService $membershipService)
    {
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
        $attach = $order->attach;

        if ($attach === null || !str_starts_with($attach, MembershipService::ATTACH_PREFIX)) {
            return;
        }

        $orderId = substr($attach, \strlen(MembershipService::ATTACH_PREFIX));

        if ($orderId === '') {
            return;
        }

        $this->membershipService->settle($orderId, $order->paidAmount, $order->paymentNo, $event->getContext());
    }
}
