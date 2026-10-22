<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrganizationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'organization_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrganizationEntity::class;
    }
}
