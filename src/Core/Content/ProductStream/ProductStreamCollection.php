<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductStream;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductStreamEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductStreamCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_stream_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamEntity::class;
    }
}
