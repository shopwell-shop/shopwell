<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\ShopwellEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentOrderEntity;
use Shopwell\Core\System\Payment\DataAbstractionLayer\PaymentRefundEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a refund was settled, before the app is notified.
 *
 * @internal
 */
#[Package('framework')]
class PaymentRefundFinishedEvent extends Event implements ShopwellEvent
{
    public function __construct(
        private readonly PaymentRefundEntity $paymentRefund,
        private readonly PaymentOrderEntity $paymentOrder,
        private readonly Context $context,
    ) {
    }

    public function getPaymentRefund(): PaymentRefundEntity
    {
        return $this->paymentRefund;
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
