<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Subscriber;

use Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\Event\PaymentOrderPaidEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Credits a wallet top-up once the gateway reports its payment as paid.
 *
 * This is the only place a top-up becomes money: the notification of the channel and the
 * reconciliation of open payments both end in {@see PaymentOrderPaidEvent}, so a payment that arrived
 * late is handled exactly like one that arrived on time.
 *
 * A payment of the shop that is not a top-up simply carries another attach marker and is ignored -
 * the gateway is shared, the settlement is not.
 *
 * @internal
 */
#[Package('checkout')]
class WalletTopUpSettlementSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly WalletTopUpService $topUpService)
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

        if ($attach === null || !str_starts_with($attach, WalletTopUpService::ATTACH_PREFIX)) {
            return;
        }

        $topUpId = substr($attach, \strlen(WalletTopUpService::ATTACH_PREFIX));

        if ($topUpId === '') {
            return;
        }

        $this->topUpService->settle($topUpId, $order->paidAmount, $order->paymentNo, $event->getContext());
    }
}
