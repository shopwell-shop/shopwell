<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationUnit\Aggregate\OrganizationUnitTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrganizationUnitTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationUnitTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'organization_unit_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrganizationUnitTranslationEntity::class;
    }
}
