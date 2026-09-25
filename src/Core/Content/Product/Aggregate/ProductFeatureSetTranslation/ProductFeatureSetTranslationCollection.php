<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductFeatureSetTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductFeatureSetTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductFeatureSetTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ProductFeatureSetTranslationEntity::class;
    }
}
