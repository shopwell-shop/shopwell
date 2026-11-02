<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Checkout\OrderReturn\Service\OrderReturnRefundSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Gives back the part of an order the customer paid with points.
 *
 * The points program is a source of a refund in the sense of
 * {@see OrderReturnRefundSource}, but not a payer: the discount it gave was never collected by a
 * gateway, so asking the channel for it would be refused, and paying it out as money would turn a
 * discount into cash. It settles its part in the form it took - in points, back into the batches they
 * came from - which is what {@see LoyaltyReturnPoints} does.
 *
 * Because the source answers before the remainder goes to the gateway, the customer ends up with the
 * same value in the same two places it was taken from, and the gateway is only asked for what it
 * really collected.
 *
 * Idempotency comes from the withdrawals themselves: a return that was already given back leaves no
 * outstanding points behind, so a retried payout finds nothing and moves nothing.
 *
 * @final
 */
#[Package('checkout')]
class LoyaltyRefundSource implements OrderReturnRefundSource
{
    /**
     * @internal
     */
    public function __construct(private readonly LoyaltyReturnPoints $points)
    {
    }

    public function refundableAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int
    {
        return $this->points->refundableValue($orderReturn, $order, $context);
    }

    public function refund(OrderReturnEntity $orderReturn, OrderEntity $order, int $amount, Context $context): void
    {
        $this->points->giveBack($orderReturn, $order, $amount, $context);
    }
}
