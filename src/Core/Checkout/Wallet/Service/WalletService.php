<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Checkout\Wallet\WalletException;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SystemConfig\SystemConfigService;

/**
 * The business rules of a customer wallet: what a booking means, how much may be paid in and when a
 * wallet may be debited.
 *
 * The balances are written by {@see WalletLedger}, never here - this service validates and delegates,
 * so there is exactly one place that can move a balance.
 *
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

    final public const CONFIG_KEY_MAX_TOP_UP_AMOUNT = 'core.wallet.maxTopUpAmount';

    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletCollection> $walletRepository
     */
    public function __construct(
        private readonly EntityRepository $walletRepository,
        private readonly WalletLedger $ledger,
        private readonly SystemConfigService $systemConfigService,
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

        $this->walletRepository->create([[
            'id' => Uuid::randomHex(),
            'customerId' => $customerId,
            'currencyId' => Defaults::CURRENCY,
            'balance' => 0.0,
            'lockedBalance' => 0.0,
        ]], $context);
    }

    /**
     * Books a manual top-up an administrator granted, e.g. a goodwill credit at the counter.
     *
     * @throws WalletException
     */
    public function topUp(string $customerId, string $currencyId, float $amount, ?string $externalReference, Context $context): float
    {
        $this->assertTopUpAmount($amount);

        return $this->ledger->book(
            $customerId,
            $currencyId,
            self::TRANSACTION_TYPE_TOP_UP,
            $amount,
            $context,
            externalReference: $externalReference
        );
    }

    /**
     * Credits a top-up the customer paid online.
     *
     * The amount was validated when the top-up was created, so it is deliberately not validated again:
     * the shop configuration may have changed while the payer was in the payment interface, and a
     * payment that really happened must never be lost because a limit moved. The top-up itself decides
     * whether it is credited once, see {@see WalletTopUpService::settle()}.
     */
    public function creditPaidTopUp(string $customerId, string $currencyId, float $amount, string $topUpId, Context $context): float
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        return $this->ledger->book(
            $customerId,
            $currencyId,
            self::TRANSACTION_TYPE_TOP_UP,
            $amount,
            $context,
            externalReference: $topUpId
        );
    }

    /**
     * @throws WalletException
     */
    public function consume(string $customerId, string $currencyId, float $amount, ?string $orderId, Context $context): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $this->ledger->book(
            $customerId,
            $currencyId,
            self::TRANSACTION_TYPE_CONSUME,
            -$amount,
            $context,
            orderId: $orderId,
            rejectNegativeBalance: true,
            oncePerOrder: $orderId !== null
        );
    }

    /**
     * @throws WalletException
     */
    public function refund(string $customerId, string $currencyId, float $amount, ?string $orderId, Context $context): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $this->ledger->book(
            $customerId,
            $currencyId,
            self::TRANSACTION_TYPE_REFUND,
            $amount,
            $context,
            orderId: $orderId
        );
    }

    /**
     * Books a manual adjustment by an administrator. The resulting balance must not become negative.
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
            self::TRANSACTION_TYPE_ADJUST,
            $amount,
            $context,
            comment: $comment,
            administratorId: $administratorId,
            rejectNegativeBalance: true
        );
    }

    /**
     * @throws WalletException
     */
    private function assertTopUpAmount(float $amount): void
    {
        if ($amount <= 0.0) {
            throw WalletException::invalidAmount($amount);
        }

        $minimum = $this->systemConfigService->getFloat(self::CONFIG_KEY_MIN_TOP_UP_AMOUNT);

        if ($minimum > 0.0 && $amount < $minimum) {
            throw WalletException::belowMinimumTopUp($amount, $minimum);
        }
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
}
