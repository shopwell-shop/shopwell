<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Aggregate\ProductReview;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ProductReviewEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class ProductReviewCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'product_review_collection';
    }

    protected function getExpectedClass(): string
    {
        return ProductReviewEntity::class;
    }
}
