<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Dictionary\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryCollection;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryEntity;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRouteResponse;
use Shopwell\Core\Test\Generator;

/**
 * The response is the storefront contract of the dictionary: the template reads `entries` and nothing
 * else, so the test pins down that the collection travels untouched and that an empty dictionary is an
 * empty collection rather than a null the template has to guard.
 *
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(DictionaryRouteResponse::class)]
class DictionaryRouteResponseTest extends TestCase
{
    public function testGetEntries(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $entry = (new DictionaryEntryEntity())->assign([
            'id' => Uuid::randomHex(),
            'dictionaryTypeId' => Uuid::randomHex(),
            'technicalName' => 'female',
        ]);
        $entries = new DictionaryEntryCollection([$entry]);

        $response = new DictionaryRouteResponse(new EntitySearchResult(
            DictionaryEntryDefinition::ENTITY_NAME,
            1,
            $entries,
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        static::assertSame($entries, $response->getEntries());
        static::assertSame($entry, $response->getEntries()->first());
        static::assertSame($response->getEntries(), $response->getObject()->getEntities());
    }

    public function testGetEntriesReturnsAnEmptyCollectionForAnEmptyDictionary(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $response = new DictionaryRouteResponse(new EntitySearchResult(
            DictionaryEntryDefinition::ENTITY_NAME,
            0,
            new DictionaryEntryCollection(),
            null,
            new Criteria(),
            $salesChannelContext->getContext(),
        ));

        static::assertCount(0, $response->getEntries());
    }
}
