<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront\Settlement;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Turns a paid payment order into what was bought, exactly once.
 *
 * The gateway reports a payment more than once - a channel retries its notification, the reconciliation
 * of open payments finds the same order again - and every kind of purchase has to survive that the same
 * way. Wallet top-ups and memberships used to implement the identical sequence twice, which is where the
 * third kind of purchase would have copied it a third time.
 *
 * The sequence is: lock the purchase, stop when it is already settled, refuse an amount the customer
 * never agreed to, and otherwise apply the purchase together with its state in the same transaction. A
 * refusal is recorded on the purchase instead of only being logged, so the operator finds the paid money
 * that bought nothing.
 *
 * @final
 */
#[Package('checkout')]
class PurchaseSettlement
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param int $paidMinor the amount the channel confirmed, in the smallest unit of the currency
     */
    public function settle(
        PurchaseStore $store,
        string $purchaseId,
        int $paidMinor,
        string $paymentNo,
        Context $context,
    ): PurchaseSettlementResult {
        $result = $this->connection->transactional(function () use ($store, $purchaseId, $paidMinor, $paymentNo, $context): PurchaseSettlementResult {
            $purchase = $store->lockForSettlement($purchaseId);

            if ($purchase === null) {
                return PurchaseSettlementResult::NotFound;
            }

            if ($purchase->isSettled()) {
                return PurchaseSettlementResult::AlreadySettled;
            }

            $now = $this->clock->now();

            if (!$purchase->isSettleable()) {
                $purchase->fail($paymentNo, $now, $context);

                return PurchaseSettlementResult::NotSettleable;
            }

            if ($purchase->amountMinor() !== $paidMinor) {
                $purchase->fail($paymentNo, $now, $context);

                return PurchaseSettlementResult::AmountMismatch;
            }

            $purchase->settle($paymentNo, $now, $context);

            return PurchaseSettlementResult::Settled;
        });

        \assert($result instanceof PurchaseSettlementResult);

        return $result;
    }
}
