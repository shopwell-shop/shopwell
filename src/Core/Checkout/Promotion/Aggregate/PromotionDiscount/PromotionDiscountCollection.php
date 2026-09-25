<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscount;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionDiscountEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionDiscountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_discount_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionDiscountEntity::class;
    }
}
