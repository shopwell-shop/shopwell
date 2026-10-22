<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<OrganizationTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'organization_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return OrganizationTranslationEntity::class;
    }
}
