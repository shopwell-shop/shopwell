<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipOrder\MembershipOrderCollection;
use Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class MembershipPlanEntity extends Entity
{
    use EntityIdTrait;

    protected string $technicalName;

    protected string $loyaltyTierId;

    protected ?LoyaltyTierEntity $loyaltyTier = null;

    protected string $currencyId;

    protected ?CurrencyEntity $currency = null;

    protected float $price;

    protected int $durationDays;

    protected bool $active = false;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?MembershipOrderCollection $orders = null;

    protected ?MembershipPlanTranslationCollection $translations = null;

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getLoyaltyTierId(): string
    {
        return $this->loyaltyTierId;
    }

    public function setLoyaltyTierId(string $loyaltyTierId): void
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

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getDurationDays(): int
    {
        return $this->durationDays;
    }

    public function setDurationDays(int $durationDays): void
    {
        $this->durationDays = $durationDays;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
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

    public function getOrders(): ?MembershipOrderCollection
    {
        return $this->orders;
    }

    public function setOrders(MembershipOrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getTranslations(): ?MembershipPlanTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MembershipPlanTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
