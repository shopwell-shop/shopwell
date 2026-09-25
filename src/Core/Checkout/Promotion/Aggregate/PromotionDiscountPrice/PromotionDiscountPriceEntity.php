<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionDiscountPriceEntity extends Entity
{
    use EntityIdTrait;

    protected string $currencyId;

    protected string $discountId;

    protected float $price;

    protected ?PromotionDiscountEntity $promotionDiscount = null;

    protected ?CurrencyEntity $currency = null;

    public function getCurrencyId(): string
    {
        return $this->currencyId;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getDiscountId(): string
    {
        return $this->discountId;
    }

    public function setDiscountId(string $discountId): void
    {
        $this->discountId = $discountId;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(?CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getPromotionDiscount(): ?PromotionDiscountEntity
    {
        return $this->promotionDiscount;
    }

    public function setPromotionDiscount(?PromotionDiscountEntity $promotionDiscount): void
    {
        $this->promotionDiscount = $promotionDiscount;
    }
}
