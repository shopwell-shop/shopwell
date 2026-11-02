<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipOrder\MembershipOrderEntity;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanEntity;
use Shopwell\Core\Checkout\Membership\MembershipSubscriptionStates;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipSubscriptionEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected ?CustomerEntity $customer = null;

    protected ?string $membershipPlanId = null;

    protected ?MembershipPlanEntity $membershipPlan = null;

    protected ?string $loyaltyTierId = null;

    protected ?LoyaltyTierEntity $loyaltyTier = null;

    protected ?string $planName = null;

    protected float $amount;

    protected ?string $currency = null;

    protected string $state;

    protected \DateTimeInterface $startsAt;

    protected \DateTimeInterface $endsAt;

    protected ?\DateTimeInterface $endedAt = null;

    protected ?string $endReason = null;

    protected ?string $lastOrderId = null;

    protected ?MembershipOrderEntity $lastOrder = null;

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

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getStartsAt(): \DateTimeInterface
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeInterface $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getEndsAt(): \DateTimeInterface
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeInterface $endsAt): void
    {
        $this->endsAt = $endsAt;
    }

    public function getEndedAt(): ?\DateTimeInterface
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeInterface $endedAt): void
    {
        $this->endedAt = $endedAt;
    }

    public function getEndReason(): ?string
    {
        return $this->endReason;
    }

    public function setEndReason(?string $endReason): void
    {
        $this->endReason = $endReason;
    }

    public function getLastOrderId(): ?string
    {
        return $this->lastOrderId;
    }

    public function setLastOrderId(?string $lastOrderId): void
    {
        $this->lastOrderId = $lastOrderId;
    }

    public function getLastOrder(): ?MembershipOrderEntity
    {
        return $this->lastOrder;
    }

    public function setLastOrder(MembershipOrderEntity $lastOrder): void
    {
        $this->lastOrder = $lastOrder;
    }

    /**
     * Whether the membership still gives the tier at the given point in time.
     */
    public function isRunningAt(\DateTimeInterface $moment): bool
    {
        return $this->state === MembershipSubscriptionStates::STATE_ACTIVE && $this->endsAt > $moment;
    }
}
