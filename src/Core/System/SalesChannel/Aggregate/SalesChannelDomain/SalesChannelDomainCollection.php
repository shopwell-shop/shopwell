<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalesChannelDomainEntity>
 */
#[Package('discovery')]
class SalesChannelDomainCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_domain_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelDomainEntity::class;
    }
}
