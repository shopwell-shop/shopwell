<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class LoyaltyTierChangedEvent extends NestedEvent
{
    public function __construct(
        private readonly string $customerId,
        private readonly ?string $previousTierId,
        private readonly ?string $newTierId,
        private readonly Context $context
    ) {
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getPreviousTierId(): ?string
    {
        return $this->previousTierId;
    }

    public function getNewTierId(): ?string
    {
        return $this->newTierId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
