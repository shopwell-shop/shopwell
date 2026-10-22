<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\Aggregate\DictionaryEntryTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DictionaryEntryTranslationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dictionary_entry_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return DictionaryEntryTranslationEntity::class;
    }
}
