<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Event\WalletBalanceChangedEvent;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The only writer of a wallet balance.
 *
 * The balance on the wallet row is a projection of the ledger: every change is one entry, and the row
 * is only ever moved together with it. Both happen inside one transaction on a row that is locked for
 * the duration of it, so two bookings of the same wallet can neither read the same balance nor
 * overwrite each other - the read-modify-write that used to live in {@see WalletService} lost one of
 * two concurrent bookings and wrote a `balanceAfter` that never existed.
 *
 * Callers keep the business rules and pass them in: whether a booking may overdraw the wallet, which
 * reference it carries and what it is called. The ledger refuses an overdraft itself, because the
 * balance it has to judge is the one it locked and not the one a caller read a moment earlier.
 *
 * @final
 */
#[Package('checkout')]
class WalletLedger
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletCollection> $walletRepository
     * @param EntityRepository<CustomerWalletTransactionCollection> $walletTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $walletRepository,
        private readonly EntityRepository $walletTransactionRepository,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Books one entry and answers the balance after it.
     *
     * The wallet is opened on first use: a customer that was created before the wallet existed - or in
     * a currency it never used - gets its wallet here instead of failing the payment. Opening it books
     * nothing, the balance belongs to the entries.
     *
     * @param bool $rejectNegativeBalance whether the booking may take the wallet below zero
     *
     * @throws WalletException when the booking would overdraw a wallet that must not be overdrawn
     */
    public function book(
        string $customerId,
        string $currencyId,
        string $type,
        float $amount,
        Context $context,
        ?string $orderId = null,
        ?string $externalReference = null,
        ?string $comment = null,
        ?string $administratorId = null,
        bool $rejectNegativeBalance = false,
        bool $oncePerOrder = false,
    ): float {
        $balanceAfter = $this->connection->transactional(function () use (
            $customerId,
            $currencyId,
            $type,
            $amount,
            $context,
            $orderId,
            $externalReference,
            $comment,
            $administratorId,
            $rejectNegativeBalance,
            $oncePerOrder
        ): float {
            $wallet = $this->lockWallet($customerId, $currencyId, $context);

            // an order may complete more than once - a retried state transition, a duplicate message -
            // and must only be debited once, which is decided under the very same lock
            if ($oncePerOrder && $orderId !== null && $this->hasEntryForOrder($wallet['id'], $orderId, $type)) {
                return $wallet['balance'];
            }

            $balanceAfter = $wallet['balance'] + $amount;

            if ($rejectNegativeBalance && $balanceAfter < 0.0) {
                throw WalletException::insufficientBalance($customerId, $currencyId, abs($amount), $wallet['balance']);
            }

            $this->walletTransactionRepository->create([[
                'customerWalletId' => $wallet['id'],
                'type' => $type,
                'amount' => $amount,
                'balanceAfter' => $balanceAfter,
                'currencyId' => $currencyId,
                'orderId' => $orderId,
                'externalReference' => $externalReference,
                'comment' => $comment,
                'administratorId' => $administratorId,
            ]], $context);

            $this->walletRepository->update([[
                'id' => $wallet['id'],
                'balance' => $balanceAfter,
            ]], $context);

            return $balanceAfter;
        });

        // only announced once the entry and the balance are committed, a listener must never see a
        // booking that can still be rolled back
        $this->eventDispatcher->dispatch(
            new WalletBalanceChangedEvent($customerId, $currencyId, $type, $amount, $balanceAfter, $context)
        );

        return $balanceAfter;
    }

    private function hasEntryForOrder(string $walletId, string $orderId, string $type): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `customer_wallet_transaction`
             WHERE `customer_wallet_id` = :walletId AND `order_id` = :orderId AND `type` = :type
             LIMIT 1',
            [
                'walletId' => Uuid::fromHexToBytes($walletId),
                'orderId' => Uuid::fromHexToBytes($orderId),
                'type' => $type,
            ],
            [
                'walletId' => ParameterType::BINARY,
                'orderId' => ParameterType::BINARY,
            ]
        );
    }

    /**
     * @return array{id: string, balance: float}
     */
    private function lockWallet(string $customerId, string $currencyId, Context $context): array
    {
        $wallet = $this->selectForUpdate($customerId, $currencyId);

        if ($wallet !== null) {
            return $wallet;
        }

        $id = Uuid::randomHex();

        try {
            $this->walletRepository->create([[
                'id' => $id,
                'customerId' => $customerId,
                'currencyId' => $currencyId,
                'balance' => 0.0,
                'lockedBalance' => 0.0,
            ]], $context);
        } catch (UniqueConstraintViolationException) {
            // a parallel booking opened the very same wallet first, its row is the one to book onto
        }

        // the insert already holds the row, but reading it back keeps one code path for the balance
        return $this->selectForUpdate($customerId, $currencyId) ?? ['id' => $id, 'balance' => 0.0];
    }

    /**
     * @return array{id: string, balance: float}|null
     */
    private function selectForUpdate(string $customerId, string $currencyId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(`id`)) AS `id`, `balance` FROM `customer_wallet`
             WHERE `customer_id` = :customerId AND `currency_id` = :currencyId
             FOR UPDATE',
            [
                'customerId' => Uuid::fromHexToBytes($customerId),
                'currencyId' => Uuid::fromHexToBytes($currencyId),
            ],
            [
                'customerId' => ParameterType::BINARY,
                'currencyId' => ParameterType::BINARY,
            ]
        );

        if (!\is_array($row)) {
            return null;
        }

        return ['id' => (string) $row['id'], 'balance' => (float) $row['balance']];
    }
}
