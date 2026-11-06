<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyAccount\LoyaltyAccountCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation\LoyaltyTierTranslationCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit\LoyaltyTierBenefitCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPlan\MembershipPlanCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipPurchase\MembershipPurchaseCollection;
use Shopwell\Core\Checkout\Membership\Entity\MembershipSubscription\MembershipSubscriptionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;

    protected int $level;

    protected ?int $minTierPoints = null;

    protected ?bool $active = null;

    protected ?string $customerGroupId = null;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?CustomerGroupEntity $customerGroup = null;

    protected ?LoyaltyAccountCollection $customers = null;

    protected ?LoyaltyTierTranslationCollection $translations = null;

    protected ?MembershipPlanCollection $membershipPlans = null;

    protected ?MembershipPurchaseCollection $membershipPurchases = null;

    protected ?MembershipSubscriptionCollection $membershipSubscriptions = null;

    protected ?LoyaltyTierBenefitCollection $benefits = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function getMinTierPoints(): ?int
    {
        return $this->minTierPoints;
    }

    public function setMinTierPoints(?int $minTierPoints): void
    {
        $this->minTierPoints = $minTierPoints;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getCustomerGroupId(): ?string
    {
        return $this->customerGroupId;
    }

    public function setCustomerGroupId(?string $customerGroupId): void
    {
        $this->customerGroupId = $customerGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCustomerGroup(): ?CustomerGroupEntity
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getCustomers(): ?LoyaltyAccountCollection
    {
        return $this->customers;
    }

    public function setCustomers(LoyaltyAccountCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getTranslations(): ?LoyaltyTierTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(LoyaltyTierTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getMembershipPlans(): ?MembershipPlanCollection
    {
        return $this->membershipPlans;
    }

    public function setMembershipPlans(MembershipPlanCollection $membershipPlans): void
    {
        $this->membershipPlans = $membershipPlans;
    }

    public function getMembershipPurchases(): ?MembershipPurchaseCollection
    {
        return $this->membershipPurchases;
    }

    public function setMembershipPurchases(MembershipPurchaseCollection $membershipPurchases): void
    {
        $this->membershipPurchases = $membershipPurchases;
    }

    public function getMembershipSubscriptions(): ?MembershipSubscriptionCollection
    {
        return $this->membershipSubscriptions;
    }

    public function setMembershipSubscriptions(MembershipSubscriptionCollection $membershipSubscriptions): void
    {
        $this->membershipSubscriptions = $membershipSubscriptions;
    }

    public function getBenefits(): ?LoyaltyTierBenefitCollection
    {
        return $this->benefits;
    }

    public function setBenefits(LoyaltyTierBenefitCollection $benefits): void
    {
        $this->benefits = $benefits;
    }
}
