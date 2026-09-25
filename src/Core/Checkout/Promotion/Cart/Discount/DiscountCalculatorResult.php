<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Cart\Discount;

use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
class DiscountCalculatorResult
{
    /**
     * @param list<DiscountCompositionItem> $compositionItems
     */
    public function __construct(
        private readonly CalculatedPrice $price,
        private readonly array $compositionItems
    ) {
    }

    public function getPrice(): CalculatedPrice
    {
        return $this->price;
    }

    /**
     * @return list<DiscountCompositionItem>
     */
    public function getCompositionItems(): array
    {
        return $this->compositionItems;
    }
}
