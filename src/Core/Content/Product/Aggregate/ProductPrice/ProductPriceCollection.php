<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductPrice;

use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductPriceEntity>
 */
#[Package('inventory')]
class ProductPriceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_price_collection';
    }

    public function filterByRuleId(string $ruleId): self
    {
        return $this->filter(static fn (ProductPriceEntity $price) => $ruleId === $price->getRuleId());
    }

    public function sortByQuantity(): void
    {
        $this->sort(static fn (ProductPriceEntity $a, ProductPriceEntity $b) => $a->getQuantityStart() <=> $b->getQuantityStart());
    }

    public function sortByPrice(Context $context): void
    {
        $this->sort(static function (ProductPriceEntity $a, ProductPriceEntity $b) use ($context) {
            $a = $a->getPrice()->first();
            $b = $b->getPrice()->first();

            if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                return ($a ? $a->getGross() : 0) <=> ($b ? $b->getGross() : 0);
            }

            return ($a ? $a->getNet() : 0) <=> ($b ? $b->getNet() : 0);
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceEntity::class;
    }
}
