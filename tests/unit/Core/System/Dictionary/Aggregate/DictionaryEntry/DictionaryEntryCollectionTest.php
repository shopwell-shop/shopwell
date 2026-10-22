<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Dictionary\Aggregate\DictionaryEntry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryEntity;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(DictionaryEntryCollection::class)]
#[Group('dictionary')]
class DictionaryEntryCollectionTest extends TestCase
{
    public function testSortByPositionSortsAscending(): void
    {
        $collection = $this->createCollection([
            ['technicalName' => 'not_specified', 'position' => 100, 'label' => 'Not specified', 'active' => true],
            ['technicalName' => 'mr', 'position' => 1, 'label' => 'Mr.', 'active' => true],
            ['technicalName' => 'mrs', 'position' => 10, 'label' => 'Mrs.', 'active' => true],
        ]);

        $collection->sortByPosition();

        static::assertSame(
            ['mr', 'mrs', 'not_specified'],
            array_values($collection->map(fn (DictionaryEntryEntity $entry) => $entry->getTechnicalName()))
        );
    }

    public function testSortByPositionIsStableByLabel(): void
    {
        $collection = $this->createCollection([
            ['technicalName' => 'b', 'position' => 5, 'label' => 'Beta', 'active' => true],
            ['technicalName' => 'a', 'position' => 5, 'label' => 'Alpha', 'active' => true],
            ['technicalName' => 'c', 'position' => 5, 'label' => 'Gamma', 'active' => true],
        ]);

        $collection->sortByPosition();

        static::assertSame(
            ['a', 'b', 'c'],
            array_values($collection->map(fn (DictionaryEntryEntity $entry) => $entry->getTechnicalName()))
        );
    }

    public function testFilterByActiveReturnsOnlyActiveEntries(): void
    {
        $collection = $this->createCollection([
            ['technicalName' => 'mr', 'position' => 1, 'label' => 'Mr.', 'active' => true],
            ['technicalName' => 'mrs', 'position' => 10, 'label' => 'Mrs.', 'active' => false],
        ]);

        $filtered = $collection->filterByActive();

        static::assertCount(1, $filtered);
        static::assertSame('mr', $filtered->first()?->getTechnicalName());
    }

    public function testFilterByActiveKeepsOriginalCollectionUntouched(): void
    {
        $collection = $this->createCollection([
            ['technicalName' => 'mr', 'position' => 1, 'label' => 'Mr.', 'active' => true],
            ['technicalName' => 'mrs', 'position' => 10, 'label' => 'Mrs.', 'active' => false],
        ]);

        $collection->filterByActive();

        static::assertCount(2, $collection);
    }

    /**
     * @param list<array{technicalName: string, position: int, label: string, active: bool}> $entries
     */
    private function createCollection(array $entries): DictionaryEntryCollection
    {
        $entities = [];
        foreach ($entries as $entry) {
            $entity = new DictionaryEntryEntity();
            $entity->setId(Uuid::randomHex());
            $entity->setDictionaryTypeId(Uuid::randomHex());
            $entity->setTechnicalName($entry['technicalName']);
            $entity->setPosition($entry['position']);
            $entity->setLabel($entry['label']);
            $entity->setActive($entry['active']);
            $entities[] = $entity;
        }

        return new DictionaryEntryCollection($entities);
    }
}
