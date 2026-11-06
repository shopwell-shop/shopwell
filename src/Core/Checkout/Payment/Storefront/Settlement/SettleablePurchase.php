<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Storefront\Settlement;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * A purchase the shop collects money for itself: a wallet top-up, a membership.
 *
 * The implementation is a row that was locked by its {@see PurchaseStore} and lives only inside the
 * running transaction - it holds everything the settlement needs to decide and to write, so the
 * settlement itself stays free of tables it does not own.
 */
#[Package('checkout')]
interface SettleablePurchase
{
    public function purchaseId(): string;

    /**
     * The amount the payment has to confirm, in the smallest unit of the currency.
     */
    public function amountMinor(): int;

    /**
     * Whether the purchase was already settled, which makes a repeated notification a no-op instead of
     * a second credit.
     */
    public function isSettled(): bool;

    /**
     * Whether the purchase can still be settled at all - a membership whose tier was removed while the
     * payment was open cannot be granted, and must fail visibly instead of silently.
     */
    public function isSettleable(): bool;

    /**
     * Applies what the purchase grants and records that it was paid, in the transaction that locked it.
     */
    public function settle(string $paymentNo, \DateTimeInterface $settledAt, Context $context): void;

    /**
     * Records a payment that cannot settle the purchase, so the operator sees it instead of losing it.
     */
    public function fail(string $paymentNo, \DateTimeInterface $failedAt, Context $context): void;
}
