<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<DictionaryEntryEntity>
 */
#[Package('fundamentals@discovery')]
class DictionaryEntryCollection extends EntityCollection
{
    /**
     * Sorts the entries by their position ascending. Entries with the same
     * position are sorted alphabetically by their label.
     */
    public function sortByPosition(): void
    {
        uasort($this->elements, static function (DictionaryEntryEntity $a, DictionaryEntryEntity $b): int {
            $position = $a->getPosition() <=> $b->getPosition();
            if ($position !== 0) {
                return $position;
            }

            return \strcasecmp((string) $a->getLabel(), (string) $b->getLabel());
        });
    }

    public function filterByActive(): self
    {
        return $this->filter(static fn (DictionaryEntryEntity $entry) => $entry->isActive());
    }

    public function getApiAlias(): string
    {
        return 'dictionary_entry_collection';
    }

    protected function getExpectedClass(): string
    {
        return DictionaryEntryEntity::class;
    }
}
