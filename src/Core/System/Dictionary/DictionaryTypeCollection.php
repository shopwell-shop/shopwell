<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DictionaryTypeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dictionary_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return DictionaryTypeEntity::class;
    }
}
