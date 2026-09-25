<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomFieldSetRelationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CustomFieldSetRelationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_field_set_relation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldSetRelationEntity::class;
    }
}
