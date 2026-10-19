<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class WalletBalanceChangedEvent extends NestedEvent
{
    public function __construct(
        private readonly string $customerId,
        private readonly string $currencyId,
        private readonly string $type,
        private readonly float $amount,
        private readonly float $balanceAfter,
        private readonly Context $context
    ) {
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getBalanceAfter(): float
    {
        return $this->balanceAfter;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
