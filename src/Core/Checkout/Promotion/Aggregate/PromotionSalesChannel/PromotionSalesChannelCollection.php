<?php
declare(strict_types=1);

namespace Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PromotionSalesChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class PromotionSalesChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'promotion_sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return PromotionSalesChannelEntity::class;
    }
}
