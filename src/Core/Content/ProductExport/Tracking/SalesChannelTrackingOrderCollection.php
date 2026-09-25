<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ProductExport\Tracking;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 *
 * @extends EntityCollection<SalesChannelTrackingOrderEntity>
 */
#[Package('discovery')]
class SalesChannelTrackingOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelTrackingOrderEntity::class;
    }
}
