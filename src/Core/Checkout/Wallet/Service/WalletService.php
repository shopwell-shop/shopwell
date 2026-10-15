<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Checkout\Wallet\Event\WalletBalanceChangedEvent;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('checkout')]
class WalletService
{
    final public const TRANSACTION_TYPE_TOP_UP = 'top_up';

    final public const TRANSACTION_TYPE_CONSUME = 'consume';

    final public const TRANSACTION_TYPE_REFUND = 'refund';

    final public const TRANSACTION_TYPE_ADJUST = 'adjust';

    final public const CONFIG_KEY_MIN_TOP_UP_AMOUNT = 'core.wallet.minTopUpAmount';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletCollection> $walletRepository
     * @param EntityRepository<CustomerWalletTransactionCollection> $walletTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $walletRepository,
        private readonly EntityRepository $walletTransactionRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getBalance(string $customerId, string $currencyId, Context $context): float
    {
        return $this->findWallet($customerId, $currencyId, $context)?->getBalance() ?? 0.0;
    }

    /**
     * Opens the wallet of a customer in the shop's default currency, so a customer owns a wallet
     * with a zero balance from the moment it exists instead of from its first booking. A wallet that
     * is already there is left untouched, which makes the call safe to repeat.
     *
     * Wallets in other currencies stay lazy: a shop that trades in several currencies opens the
     * others when they are first paid into, so a customer does not collect wallets it never uses.
     * The balance is owned by the ledger, so opening the wallet books nothing.
     */
    public function provisionAccount(string $customerId, Context $context): void
    {
        if ($this->findWallet($customerId, Defaults::CURRENCY, $context) !== null) {
            return;
        }

        $this->createWallet($customerId, Defaults::CURRENCY, $context);
    }

    public function topUp(string $customerId, string $currencyId, float $amount, ?string $externalReference, Context $context): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $minimum = $this->systemConfigService->getFloat(self::CONFIG_KEY_MIN_TOP_UP_AMOUNT);

        if ($minimum > 0.0 && $amount < $minimum) {
            throw WalletException::belowMinimumTopUp($amount, $minimum);
        }

        $wallet = $this->resolveWallet($customerId, $currencyId, $context);

        $this->book($wallet, self::TRANSACTION_TYPE_TOP_UP, $amount, $externalReference, null, null, null, $context);
    }

    public function consume(string $customerId, string $currencyId, float $amount, ?string $orderId, Context $context): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $wallet = $this->findWallet($customerId, $currencyId, $context);
        $balance = $wallet?->getBalance() ?? 0.0;

        // the wallet must not be overdrawn, so a too high amount is rejected
        if ($wallet === null || $balance < $amount) {
            throw WalletException::insufficientBalance($customerId, $currencyId, $amount, $balance);
        }

        // a wallet payment is booked once per order, so a repeated completion does not debit twice
        if ($orderId !== null && $this->hasConsumeForOrder($wallet, $orderId, $context)) {
            return;
        }

        $this->book($wallet, self::TRANSACTION_TYPE_CONSUME, -$amount, null, $orderId, null, null, $context);
    }

    public function refund(string $customerId, string $currencyId, float $amount, ?string $orderId, Context $context): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $wallet = $this->resolveWallet($customerId, $currencyId, $context);

        $this->book($wallet, self::TRANSACTION_TYPE_REFUND, $amount, null, $orderId, null, null, $context);
    }

    /**
     * Books a manual adjustment by an administrator. The resulting balance must not become negative.
     */
    public function adjust(string $customerId, string $currencyId, float $amount, string $comment, ?string $administratorId, Context $context): void
    {
        if ($amount === 0.0) {
            return;
        }

        $wallet = $this->resolveWallet($customerId, $currencyId, $context);

        if ($wallet->getBalance() + $amount < 0.0) {
            throw WalletException::insufficientBalance($customerId, $currencyId, abs($amount), $wallet->getBalance());
        }

        $this->book($wallet, self::TRANSACTION_TYPE_ADJUST, $amount, null, null, $comment, $administratorId, $context);
    }

    private function book(
        CustomerWalletEntity $wallet,
        string $type,
        float $amount,
        ?string $externalReference,
        ?string $orderId,
        ?string $comment,
        ?string $administratorId,
        Context $context
    ): void {
        $balanceAfter = $wallet->getBalance() + $amount;

        $this->walletTransactionRepository->create([[
            'customerWalletId' => $wallet->getId(),
            'type' => $type,
            'amount' => $amount,
            'balanceAfter' => $balanceAfter,
            'currencyId' => $wallet->getCurrencyId(),
            'orderId' => $orderId,
            'externalReference' => $externalReference,
            'comment' => $comment,
            'administratorId' => $administratorId,
        ]], $context);

        $this->walletRepository->update([[
            'id' => $wallet->getId(),
            'balance' => $balanceAfter,
        ]], $context);

        $wallet->setBalance($balanceAfter);

        $this->eventDispatcher->dispatch(
            new WalletBalanceChangedEvent(
                $wallet->getCustomerId(),
                $wallet->getCurrencyId(),
                $type,
                $amount,
                $balanceAfter,
                $context
            )
        );
    }

    private function resolveWallet(string $customerId, string $currencyId, Context $context): CustomerWalletEntity
    {
        return $this->findWallet($customerId, $currencyId, $context) ?? $this->createWallet($customerId, $currencyId, $context);
    }

    private function hasConsumeForOrder(CustomerWalletEntity $wallet, string $orderId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerWalletId', $wallet->getId()),
            new EqualsFilter('orderId', $orderId),
            new EqualsFilter('type', self::TRANSACTION_TYPE_CONSUME),
        );
        $criteria->setLimit(1);

        return $this->walletTransactionRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    private function findWallet(string $customerId, string $currencyId, Context $context): ?CustomerWalletEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('currencyId', $currencyId)
        );
        $criteria->setLimit(1);

        $wallet = $this->walletRepository->search($criteria, $context)->getEntities()->first();

        return $wallet instanceof CustomerWalletEntity ? $wallet : null;
    }

    private function createWallet(string $customerId, string $currencyId, Context $context): CustomerWalletEntity
    {
        $id = Uuid::randomHex();

        $this->walletRepository->create([[
            'id' => $id,
            'customerId' => $customerId,
            'currencyId' => $currencyId,
            'balance' => 0.0,
            'lockedBalance' => 0.0,
        ]], $context);

        $wallet = new CustomerWalletEntity();
        $wallet->setId($id);
        $wallet->setCustomerId($customerId);
        $wallet->setCurrencyId($currencyId);
        $wallet->setBalance(0.0);
        $wallet->setLockedBalance(0.0);

        return $wallet;
    }
}
