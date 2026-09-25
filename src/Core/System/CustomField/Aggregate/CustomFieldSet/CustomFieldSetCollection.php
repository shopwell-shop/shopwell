<?php declare(strict_types=1);

namespace Shopwell\Core\System\CustomField\Aggregate\CustomFieldSet;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomFieldSetEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class CustomFieldSetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_field_set_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldSetEntity::class;
    }
}
