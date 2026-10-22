<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrganizationUnitEntity>
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'organization_unit_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrganizationUnitEntity::class;
    }
}
