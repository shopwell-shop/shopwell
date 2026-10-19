<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTransaction;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Wallet\Entity\CustomerWallet\CustomerWalletEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletTransactionEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerWalletId;

    protected string $type;

    protected float $amount;

    protected float $balanceAfter;

    protected ?string $currencyId = null;

    protected ?string $orderId = null;

    protected ?string $externalReference = null;

    protected ?string $comment = null;

    protected ?string $administratorId = null;

    protected ?CustomerWalletEntity $wallet = null;

    protected ?OrderEntity $order = null;

    protected ?CurrencyEntity $currency = null;

    public function getCustomerWalletId(): string
    {
        return $this->customerWalletId;
    }

    public function setCustomerWalletId(string $customerWalletId): void
    {
        $this->customerWalletId = $customerWalletId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getBalanceAfter(): float
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(float $balanceAfter): void
    {
        $this->balanceAfter = $balanceAfter;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(string $externalReference): void
    {
        $this->externalReference = $externalReference;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getAdministratorId(): ?string
    {
        return $this->administratorId;
    }

    public function setAdministratorId(string $administratorId): void
    {
        $this->administratorId = $administratorId;
    }

    public function getWallet(): ?CustomerWalletEntity
    {
        return $this->wallet;
    }

    public function setWallet(CustomerWalletEntity $wallet): void
    {
        $this->wallet = $wallet;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }
}
