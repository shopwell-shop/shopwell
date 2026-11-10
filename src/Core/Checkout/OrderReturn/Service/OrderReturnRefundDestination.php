<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * A monetary source that can also receive funds collected by the gateway.
 * Non-monetary sources keep their original form, such as redeemed points.
 *
 * @internal
 */
#[Package('after-sales')]
interface OrderReturnRefundDestination extends OrderReturnRefundSource
{
    public function getDestination(): string;

    public function refundableDestinationAmount(OrderReturnEntity $orderReturn, OrderEntity $order, Context $context): int;
}
