<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamFilterEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductStreamFilterCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_stream_filter_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamFilterEntity::class;
    }
}
