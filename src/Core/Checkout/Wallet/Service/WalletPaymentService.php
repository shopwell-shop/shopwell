<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * The two directions an order and a wallet can move money in.
 *
 * Both are keyed on the order, so a state transition that is replayed - an order completed twice, a
 * return booked twice - books once. The rules that decide whether the wallet may carry the amount live
 * here, the balance itself is moved by {@see WalletLedger}.
 *
 * @final
 */
#[Package('checkout')]
class WalletPaymentService
{
    /**
     * @internal
     */
    public function __construct(private readonly WalletLedger $ledger)
    {
    }

    /**
     * Takes what the customer paid with the wallet when the order completes.
     *
     * @throws WalletException when the amount is invalid or the wallet no longer holds it
     */
    public function debitForOrder(string $orderId, string $customerId, string $currencyId, float $amount, Context $context): void
    {
        $this->assertPositive($amount);

        $this->ledger->book(
            $customerId,
            $currencyId,
            WalletTransactionTypes::CONSUME,
            -$amount,
            $context,
            orderId: $orderId,
            rejectNegativeBalance: true,
            oncePerOrder: true
        );
    }

    /**
     * Gives back what the customer had paid with the wallet when the order is returned.
     *
     * The booking is keyed on `$reference` - the number of the return - and not on the order: an order
     * can come back in parts, so a second return still has to be paid out while a replay of the same
     * return must not be. Without a reference it falls back to one refund per order.
     *
     * @throws WalletException when the amount is invalid
     */
    public function refundForOrder(
        string $orderId,
        string $customerId,
        string $currencyId,
        float $amount,
        Context $context,
        ?string $reference = null,
    ): void {
        $this->assertPositive($amount);

        $this->ledger->book(
            $customerId,
            $currencyId,
            WalletTransactionTypes::REFUND,
            $amount,
            $context,
            orderId: $orderId,
            externalReference: $reference,
            oncePerOrder: $reference === null,
            oncePerReference: $reference !== null,
        );
    }

    /**
     * @throws WalletException
     */
    private function assertPositive(float $amount): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }
    }
}
