<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductSearchConfigField;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSearchConfigFieldEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSearchConfigFieldCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_config_field_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchConfigFieldEntity::class;
    }
}
