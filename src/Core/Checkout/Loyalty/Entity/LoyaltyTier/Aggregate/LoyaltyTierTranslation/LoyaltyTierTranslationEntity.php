<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\Aggregate\LoyaltyTierTranslation;

use Shopwell\Core\Checkout\Loyalty\Entity\LoyaltyTier\LoyaltyTierEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class LoyaltyTierTranslationEntity extends TranslationEntity
{
    use EntityIdTrait;

    protected string $loyaltyTierId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?LoyaltyTierEntity $loyaltyTier = null;

    public function getLoyaltyTierId(): string
    {
        return $this->loyaltyTierId;
    }

    public function setLoyaltyTierId(string $loyaltyTierId): void
    {
        $this->loyaltyTierId = $loyaltyTierId;
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

    public function getLoyaltyTier(): ?LoyaltyTierEntity
    {
        return $this->loyaltyTier;
    }

    public function setLoyaltyTier(LoyaltyTierEntity $loyaltyTier): void
    {
        $this->loyaltyTier = $loyaltyTier;
    }
}
