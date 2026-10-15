<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class LoyaltyPointsRedeemedEvent extends NestedEvent
{
    public function __construct(
        private readonly string $customerId,
        private readonly int $points,
        private readonly ?string $orderId,
        private readonly Context $context
    ) {
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
