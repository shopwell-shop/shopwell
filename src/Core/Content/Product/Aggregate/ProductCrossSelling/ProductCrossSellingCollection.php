<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductCrossSelling;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductCrossSellingEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductCrossSellingCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_cross_selling_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductCrossSellingEntity::class;
    }
}
