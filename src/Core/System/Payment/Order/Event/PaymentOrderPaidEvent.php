<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Order\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a channel confirmed a payment order as paid, before the app is notified.
 *
 * @internal
 */
#[Package('framework')]
class PaymentOrderPaidEvent extends Event implements ShopwellEvent
{
    public function __construct(
        private readonly PaymentOrderEntity $paymentOrder,
        private readonly Context $context,
    ) {
    }

    public function getPaymentOrder(): PaymentOrderEntity
    {
        return $this->paymentOrder;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
