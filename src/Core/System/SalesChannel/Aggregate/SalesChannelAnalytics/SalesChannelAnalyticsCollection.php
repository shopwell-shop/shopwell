<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalesChannelAnalyticsEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelAnalyticsCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_analytics_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelAnalyticsEntity::class;
    }
}
