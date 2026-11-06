<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Value of an order that was settled somewhere other than the payment gateway.
 *
 * A return is paid out of the sources the order was paid with: whatever was settled outside the
 * gateway is given back first, and the gateway is only asked for the rest. Without that split a partly
 * wallet-paid order cannot be refunded at all - the gateway would be asked for money it never
 * collected and refuse the whole payout.
 *
 * A source gives back in the form it took: money returns to the wallet, a points discount returns as
 * points into the batches it came from. Speaking in the minor unit of the currency is what lets the
 * parts be added up in one place, not a promise that the source pays out money.
 *
 * Implementations answer in the minor unit of the currency of the return, and must be idempotent per
 * return: the payout of a return may be retried after a channel failed, and the customer must not be
 * paid twice for the same return.
 *
 * @internal
 */
#[Package('after-sales')]
interface OrderReturnRefundSource
{
    /**
     * How much of this order is still refundable through this source, in the minor unit of the currency.
     */
    public function refundableAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int;

    /**
     * Pays out $amount of the return through this source, at most once per return.
     */
    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, int $amount, Context $context): void;
}
