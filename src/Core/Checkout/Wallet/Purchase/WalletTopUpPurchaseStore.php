<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Purchase;

use Shopwell\Core\Checkout\Ledger\RowLock;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\PurchaseStore;
use Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * Locks one top-up for the settlement that is about to credit it.
 *
 * @final
 */
#[Package('checkout')]
class WalletTopUpPurchaseStore implements PurchaseStore
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletTopUpCollection> $topUpRepository
     */
    public function __construct(
        private readonly EntityRepository $topUpRepository,
        private readonly WalletLedger $ledger,
        private readonly RowLock $rows,
    ) {
    }

    public function lockForSettlement(string $purchaseId): ?SettleablePurchase
    {
        $row = $this->rows->lock('customer_wallet_top_up', ['id' => $purchaseId]);

        if ($row === null) {
            return null;
        }

        return new WalletTopUpPurchase($row, $this->topUpRepository, $this->ledger);
    }
}
