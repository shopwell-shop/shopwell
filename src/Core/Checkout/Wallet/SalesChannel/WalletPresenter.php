<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\SalesChannel;

use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp\CustomerWalletTopUpEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * The shapes the account page reads.
 *
 * Kept apart from the services so a change of the page never changes how a booking is decided, and so
 * the same wallet answers the same way from every route that shows it.
 *
 * @final
 */
#[Package('checkout')]
class WalletPresenter
{
    /**
     * @return array{id: string, currencyId: string, balance: float, lockedBalance: float|null}
     */
    public function wallet(CustomerWalletEntity $wallet): array
    {
        return [
            'id' => $wallet->getId(),
            'currencyId' => $wallet->getCurrencyId(),
            'balance' => $wallet->getBalance(),
            'lockedBalance' => $wallet->getLockedBalance(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transaction(CustomerWalletTransactionEntity $transaction): array
    {
        return [
            'id' => $transaction->getId(),
            'type' => $transaction->getType(),
            'amount' => $transaction->getAmount(),
            'balanceAfter' => $transaction->getBalanceAfter(),
            'currencyId' => $transaction->getCurrencyId(),
            'orderId' => $transaction->getOrderId(),
            'externalReference' => $transaction->getExternalReference(),
            'comment' => $transaction->getComment(),
            'createdAt' => $transaction->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topUp(CustomerWalletTopUpEntity $topUp): array
    {
        return [
            'id' => $topUp->getId(),
            'amount' => $topUp->getAmount(),
            'currency' => $topUp->getCurrency(),
            'state' => $topUp->getState(),
            'paymentNo' => $topUp->getPaymentNo(),
            'channel' => $topUp->getChannelTechnicalName(),
            'creditedAt' => $topUp->getCreditedAt()?->format(\DATE_ATOM),
            'createdAt' => $topUp->getCreatedAt()?->format(\DATE_ATOM),
        ];
    }
}
