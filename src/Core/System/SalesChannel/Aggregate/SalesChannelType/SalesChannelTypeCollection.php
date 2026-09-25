<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<SalesChannelTypeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelTypeCollection extends EntityCollection
{
    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->flatMap(static fn (SalesChannelTypeEntity $salesChannel) => $salesChannel->getSalesChannels())
        );
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeEntity::class;
    }
}
