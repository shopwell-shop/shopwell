<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class CalculatedCheapestPrice extends CalculatedPrice
{
    protected bool $hasRange = false;

    protected ?string $variantId = null;

    public function hasRange(): bool
    {
        return $this->hasRange;
    }

    public function setHasRange(bool $hasRange): void
    {
        $this->hasRange = $hasRange;
    }

    public function getApiAlias(): string
    {
        return 'calculated_cheapest_price';
    }

    public function setVariantId(string $variantId): void
    {
        $this->variantId = $variantId;
    }

    public function getVariantId(): ?string
    {
        return $this->variantId;
    }
}
