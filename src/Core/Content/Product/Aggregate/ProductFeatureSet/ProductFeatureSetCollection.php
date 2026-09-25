<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductFeatureSet;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductFeatureSetEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductFeatureSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductFeatureSetEntity::class;
    }
}
