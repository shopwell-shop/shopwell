<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Money an operator puts on or takes off a wallet.
 *
 * Both go through the ledger like every other booking, so a goodwill credit and a correction are
 * visible in the customer's statement instead of changing a balance behind its back. What the shop
 * allows for a top-up is decided here - an amount below the configured minimum is refused before it
 * reaches the ledger.
 *
 * @final
 */
#[Package('checkout')]
class WalletAdjustmentService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly WalletSettings $settings,
    ) {
    }

    /**
     * Books a top-up an administrator granted, e.g. a goodwill credit at the counter.
     *
     * @throws WalletException
     */
    public function topUp(string $customerId, string $currencyId, float $amount, ?string $externalReference, Context $context): float
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $minimum = $this->settings->minTopUpAmount();

        if ($minimum > 0.0 && $amount < $minimum) {
            throw WalletException::belowMinimumTopUp($amount, $minimum);
        }

        return $this->ledger->book(
            $customerId,
            $currencyId,
            WalletTransactionTypes::TOP_UP,
            $amount,
            $context,
            externalReference: $externalReference
        );
    }

    /**
     * Books a manual correction. The resulting balance must not become negative - an operator correcting
     * a mistake must not be able to create money the shop never received.
     *
     * @throws WalletException
     */
    public function adjust(string $customerId, string $currencyId, float $amount, string $comment, ?string $administratorId, Context $context): void
    {
        if ($amount === 0.0) {
            return;
        }

        $this->ledger->book(
            $customerId,
            $currencyId,
            WalletTransactionTypes::ADJUST,
            $amount,
            $context,
            comment: $comment,
            administratorId: $administratorId,
            rejectNegativeBalance: true
        );
    }
}
