<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductCrossSellingTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductCrossSellingTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductCrossSellingTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_cross_selling_assigned_products_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductCrossSellingTranslationEntity::class;
    }
}
