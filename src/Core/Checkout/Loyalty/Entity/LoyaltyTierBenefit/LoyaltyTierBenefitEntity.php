<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTierBenefit;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierBenefitEntity extends Entity
{
    use EntityIdTrait;

    protected string $loyaltyTierId;

    protected string $type;

    protected float $value;

    protected int $position = 0;

    protected ?LoyaltyTierEntity $loyaltyTier = null;

    public function getLoyaltyTierId(): string
    {
        return $this->loyaltyTierId;
    }

    public function setLoyaltyTierId(string $loyaltyTierId): void
    {
        $this->loyaltyTierId = $loyaltyTierId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function setValue(float $value): void
    {
        $this->value = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getLoyaltyTier(): ?LoyaltyTierEntity
    {
        return $this->loyaltyTier;
    }

    public function setLoyaltyTier(LoyaltyTierEntity $loyaltyTier): void
    {
        $this->loyaltyTier = $loyaltyTier;
    }
}
