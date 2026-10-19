<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction\CustomerWalletTransactionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected string $currencyId;

    protected float $balance;

    protected ?float $lockedBalance = null;

    protected ?CustomerEntity $customer = null;

    protected ?CurrencyEntity $currency = null;

    protected ?CustomerWalletTransactionCollection $transactions = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function setBalance(float $balance): void
    {
        $this->balance = $balance;
    }

    public function getLockedBalance(): ?float
    {
        return $this->lockedBalance;
    }

    public function setLockedBalance(?float $lockedBalance): void
    {
        $this->lockedBalance = $lockedBalance;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getTransactions(): ?CustomerWalletTransactionCollection
    {
        return $this->transactions;
    }

    public function setTransactions(CustomerWalletTransactionCollection $transactions): void
    {
        $this->transactions = $transactions;
    }
}
