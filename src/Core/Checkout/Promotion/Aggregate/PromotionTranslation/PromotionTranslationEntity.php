<?php
declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionTranslation;

use Shopwell\Core\Checkout\Promotion\PromotionEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $promotionId;

    protected ?string $name = null;

    protected ?PromotionEntity $promotion = null;

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function setPromotionId(string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
    }
}
