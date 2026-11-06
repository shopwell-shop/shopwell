<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Service;

use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletCollection;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Reads a customer wallet and opens it when the customer gets one.
 *
 * The balance is answered from the wallet row, which is a projection the ledger maintains - this service
 * never writes a balance, only the empty row it is projected onto.
 *
 * @final
 */
#[Package('checkout')]
class WalletAccountService
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerWalletCollection> $walletRepository
     */
    public function __construct(private readonly EntityRepository $walletRepository)
    {
    }

    public function balance(string $customerId, string $currencyId, Context $context): float
    {
        return $this->wallet($customerId, $currencyId, $context)?->getBalance() ?? 0.0;
    }

    public function wallet(string $customerId, string $currencyId, Context $context): ?CustomerWalletEntity
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

    /**
     * Opens the wallet of a customer in one currency, so a customer owns a wallet with a zero balance
     * from the moment it exists instead of from its first booking. A wallet that is already there is
     * left untouched, which makes the call safe to repeat.
     *
     * Wallets in other currencies stay lazy: a shop that trades in several currencies opens the others
     * when they are first paid into, so a customer does not collect wallets it never uses. The balance
     * belongs to the ledger, so opening a wallet books nothing.
     */
    public function open(string $customerId, string $currencyId, Context $context): void
    {
        if ($this->wallet($customerId, $currencyId, $context) !== null) {
            return;
        }

        $this->walletRepository->create([[
            'id' => Uuid::randomHex(),
            'customerId' => $customerId,
            'currencyId' => $currencyId,
            'balance' => 0.0,
            'lockedBalance' => 0.0,
        ]], $context);
    }
}
