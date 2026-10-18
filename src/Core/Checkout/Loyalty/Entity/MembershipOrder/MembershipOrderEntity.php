<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipOrderEntity extends Entity
{
    use EntityIdTrait;

    final public const STATE_OPEN = 'open';

    final public const STATE_ACTIVATED = 'activated';

    final public const STATE_FAILED = 'failed';

    protected string $customerId;

    protected ?CustomerEntity $customer = null;

    protected ?string $membershipPlanId = null;

    protected ?MembershipPlanEntity $membershipPlan = null;

    protected ?string $loyaltyTierId = null;

    protected ?LoyaltyTierEntity $loyaltyTier = null;

    protected ?string $planName = null;

    protected float $amount;

    protected string $currencyId;

    protected string $currency;

    protected int $durationDays;

    protected string $state = self::STATE_OPEN;

    protected ?string $paymentNo = null;

    protected ?string $channelTechnicalName = null;

    protected ?\DateTimeInterface $startsAt = null;

    protected ?\DateTimeInterface $expiresAt = null;

    protected ?\DateTimeInterface $paidAt = null;

    protected ?\DateTimeInterface $activatedAt = null;

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

    public function getMembershipPlanId(): ?string
    {
        return $this->membershipPlanId;
    }

    public function setMembershipPlanId(?string $membershipPlanId): void
    {
        $this->membershipPlanId = $membershipPlanId;
    }

    public function getMembershipPlan(): ?MembershipPlanEntity
    {
        return $this->membershipPlan;
    }

    public function setMembershipPlan(MembershipPlanEntity $membershipPlan): void
    {
        $this->membershipPlan = $membershipPlan;
    }

    public function getLoyaltyTierId(): ?string
    {
        return $this->loyaltyTierId;
    }

    public function setLoyaltyTierId(?string $loyaltyTierId): void
    {
        $this->loyaltyTierId = $loyaltyTierId;
    }

    public function getLoyaltyTier(): ?LoyaltyTierEntity
    {
        return $this->loyaltyTier;
    }

    public function setLoyaltyTier(LoyaltyTierEntity $loyaltyTier): void
    {
        $this->loyaltyTier = $loyaltyTier;
    }

    public function getPlanName(): ?string
    {
        return $this->planName;
    }

    public function setPlanName(?string $planName): void
    {
        $this->planName = $planName;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
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

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): void
    {
        $this->durationDays = $durationDays;
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

    public function getChannelTechnicalName(): ?string
    {
        return $this->channelTechnicalName;
    }

    public function setChannelTechnicalName(?string $channelTechnicalName): void
    {
        $this->channelTechnicalName = $channelTechnicalName;
    }

    public function getStartsAt(): ?\DateTimeInterface
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeInterface $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): void
    {
        $this->paidAt = $paidAt;
    }

    public function getActivatedAt(): ?\DateTimeInterface
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(?\DateTimeInterface $activatedAt): void
    {
        $this->activatedAt = $activatedAt;
    }

    public function isActivated(): bool
    {
        return $this->state === self::STATE_ACTIVATED;
    }
}
