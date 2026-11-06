<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront\Settlement;

use Shopwell\Core\Framework\Log\Package;

/**
 * Finds and locks the purchases of one kind.
 *
 * Implemented by the service that owns the purchase table, because only it knows how its row becomes a
 * {@see SettleablePurchase}. The lock has to be taken inside the settlement transaction, which is why
 * this is a lookup and not a plain repository read.
 */
#[Package('checkout')]
interface PurchaseStore
{
    public function lockForSettlement(string $purchaseId): ?SettleablePurchase;
}
