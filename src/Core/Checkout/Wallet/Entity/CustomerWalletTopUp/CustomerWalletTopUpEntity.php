<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Entity\CustomerWalletTopUp;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWalletTopUpEntity extends Entity
{
    use EntityIdTrait;

    final public const STATE_OPEN = 'open';

    final public const STATE_PAID = 'paid';

    final public const STATE_CREDITED = 'credited';

    final public const STATE_FAILED = 'failed';

    protected string $customerId;

    protected ?CustomerEntity $customer = null;

    protected string $currencyId;

    protected string $currency;

    protected float $amount;

    protected string $state = self::STATE_OPEN;

    protected ?string $paymentNo = null;

    protected ?string $providerTechnicalName = null;

    protected ?string $channelTechnicalName = null;

    protected ?\DateTimeInterface $paidAt = null;

    protected ?\DateTimeInterface $creditedAt = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getPaymentNo(): ?string
    {
        return $this->paymentNo;
    }

    public function setPaymentNo(?string $paymentNo): void
    {
        $this->paymentNo = $paymentNo;
    }

    public function getProviderTechnicalName(): ?string
    {
        return $this->providerTechnicalName;
    }

    public function setProviderTechnicalName(?string $providerTechnicalName): void
    {
        $this->providerTechnicalName = $providerTechnicalName;
    }

    public function getChannelTechnicalName(): ?string
    {
        return $this->channelTechnicalName;
    }

    public function setChannelTechnicalName(?string $channelTechnicalName): void
    {
        $this->channelTechnicalName = $channelTechnicalName;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function getCreditedAt(): ?\DateTimeInterface
    {
        return $this->creditedAt;
    }

    public function setCreditedAt(?\DateTimeInterface $creditedAt): void
    {
        $this->creditedAt = $creditedAt;
    }

    public function isCredited(): bool
    {
        return $this->state === self::STATE_CREDITED;
    }
}
