<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Purchase;

use Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Service\WalletLedger;
use Shopwell\Core\Checkout\Wallet\WalletTransactionTypes;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;

/**
 * A paid top-up waiting to become money on the wallet.
 *
 * The row is locked by {@see \Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService} for the
 * settlement that builds this object, so the credit and the state it records cannot be separated by a
 * second notification of the same payment.
 *
 * @final
 */
#[Package('checkout')]
class WalletTopUpPurchase implements SettleablePurchase
{
    /**
     * @internal
     *
     * @param array<string, mixed> $row the locked `customer_wallet_top_up` row
     * @param EntityRepository<CustomerWalletTopUpCollection> $topUpRepository
     */
    public function __construct(
        private readonly array $row,
        private readonly EntityRepository $topUpRepository,
        private readonly WalletLedger $ledger,
    ) {
    }

    public function purchaseId(): string
    {
        return (string) $this->row['id'];
    }

    public function amountMinor(): int
    {
        return CurrencyAmountConverter::fromFloat((float) $this->row['amount'], (string) $this->row['currency']);
    }

    public function isSettled(): bool
    {
        return $this->row['state'] === CustomerWalletTopUpEntity::STATE_CREDITED;
    }

    public function isSettleable(): bool
    {
        return true;
    }

    public function settle(string $paymentNo, \DateTimeInterface $settledAt, Context $context): void
    {
        $this->ledger->book(
            (string) $this->row['customer_id'],
            (string) $this->row['currency_id'],
            WalletTransactionTypes::TOP_UP,
            (float) $this->row['amount'],
            $context,
            externalReference: $this->purchaseId()
        );

        $this->topUpRepository->update([[
            'id' => $this->purchaseId(),
            'state' => CustomerWalletTopUpEntity::STATE_CREDITED,
            'paymentNo' => $paymentNo,
            'paidAt' => $settledAt,
            'creditedAt' => $settledAt,
        ]], $context);
    }

    public function fail(string $paymentNo, \DateTimeInterface $failedAt, Context $context): void
    {
        $this->topUpRepository->update([[
            'id' => $this->purchaseId(),
            'state' => CustomerWalletTopUpEntity::STATE_FAILED,
            'paymentNo' => $paymentNo,
            'paidAt' => $failedAt,
        ]], $context);
    }
}
