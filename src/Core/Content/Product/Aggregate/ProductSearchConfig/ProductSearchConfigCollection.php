<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductSearchConfig;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSearchConfigEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSearchConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchConfigEntity::class;
    }
}
