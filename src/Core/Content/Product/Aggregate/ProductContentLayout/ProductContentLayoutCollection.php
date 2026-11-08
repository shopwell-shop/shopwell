<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<ProductContentLayoutEntity>
 */
#[Package('discovery')]
class ProductContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductContentLayoutEntity::class;
    }
}
