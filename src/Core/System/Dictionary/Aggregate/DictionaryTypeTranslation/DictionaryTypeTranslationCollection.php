<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryTypeTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DictionaryTypeTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryTypeTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dictionary_type_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DictionaryTypeTranslationEntity::class;
    }
}
