<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductSearchKeywordEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductSearchKeywordCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_search_keyword_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordEntity::class;
    }
}
