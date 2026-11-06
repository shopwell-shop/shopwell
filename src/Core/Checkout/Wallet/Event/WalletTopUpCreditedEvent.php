<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * A top-up became money on the wallet.
 *
 * This is the fact other modules may build on: the amount is on the balance, the row says credited, and
 * the settlement that decided it is committed. It is deliberately not the same thing as
 * {@see WalletBalanceChangedEvent} - that one says the balance moved, which happens on every payment
 * and every refund too, and it carries no top-up to point at.
 *
 * The top-up id is what a listener makes its work idempotent with: a payment channel may report the
 * same payment again, and the shop must not hand out the same reward twice because of it.
 *
 * @final
 */
#[Package('checkout')]
class WalletTopUpCreditedEvent extends NestedEvent
{
    /**
     * @param string $currency the ISO code, so a listener does not have to load the currency row
     * @param float $amount the amount the customer paid, in that currency
     */
    public function __construct(
        private readonly string $topUpId,
        private readonly string $customerId,
        private readonly string $currency,
        private readonly float $amount,
        private readonly Context $context,
    ) {
    }

    public function getTopUpId(): string
    {
        return $this->topUpId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
