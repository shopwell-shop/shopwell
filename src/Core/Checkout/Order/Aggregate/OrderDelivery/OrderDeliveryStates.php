<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery;

use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
final class OrderDeliveryStates
{
    public const STATE_MACHINE = 'order_delivery.state';
    public const STATE_OPEN = 'open';
    public const STATE_PARTIALLY_SHIPPED = 'shipped_partially';
    public const STATE_SHIPPED = 'shipped';
    public const STATE_RETURNED = 'returned';
    public const STATE_PARTIALLY_RETURNED = 'returned_partially';
    public const STATE_CANCELLED = 'cancelled';
}
