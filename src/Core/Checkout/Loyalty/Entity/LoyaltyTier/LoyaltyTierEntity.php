<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\CustomerLoyalty\CustomerLoyaltyCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation\LoyaltyTierTranslationCollection;
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

    protected ?int $minGrowthValue = null;

    protected ?float $pointsMultiplier = null;

    protected ?float $discountPercent = null;

    protected ?bool $freeShipping = null;

    protected ?bool $active = null;

    protected ?string $customerGroupId = null;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?CustomerGroupEntity $customerGroup = null;

    protected ?CustomerLoyaltyCollection $customers = null;

    protected ?LoyaltyTierTranslationCollection $translations = null;

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

    public function getMinGrowthValue(): ?int
    {
        return $this->minGrowthValue;
    }

    public function setMinGrowthValue(?int $minGrowthValue): void
    {
        $this->minGrowthValue = $minGrowthValue;
    }

    public function getPointsMultiplier(): ?float
    {
        return $this->pointsMultiplier;
    }

    public function setPointsMultiplier(?float $pointsMultiplier): void
    {
        $this->pointsMultiplier = $pointsMultiplier;
    }

    public function getDiscountPercent(): ?float
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?float $discountPercent): void
    {
        $this->discountPercent = $discountPercent;
    }

    public function getFreeShipping(): ?bool
    {
        return $this->freeShipping;
    }

    public function setFreeShipping(?bool $freeShipping): void
    {
        $this->freeShipping = $freeShipping;
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

    public function getCustomers(): ?CustomerLoyaltyCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerLoyaltyCollection $customers): void
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
}
