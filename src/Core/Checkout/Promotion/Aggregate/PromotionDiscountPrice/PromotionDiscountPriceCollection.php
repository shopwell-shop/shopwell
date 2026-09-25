<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionDiscountPriceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionDiscountPriceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_discount_price_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionDiscountPriceEntity::class;
    }
}
