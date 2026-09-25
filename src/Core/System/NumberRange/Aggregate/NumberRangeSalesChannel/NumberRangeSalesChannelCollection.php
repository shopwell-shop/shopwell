<?php declare(strict_types=1);

namespace Shopwell\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NumberRangeSalesChannelEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeSalesChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_sales_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeSalesChannelEntity::class;
    }
}
