<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingResult::class)]
class ProductListingResultTest extends TestCase
{
    public function testCreateFromCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $listing = ProductListingResult::createFrom($source);

        static::assertSame($source->getTotal(), $listing->getTotal());
        static::assertSame($source->getEntities(), $listing->getEntities());
        static::assertSame($source->getCriteria(), $listing->getCriteria());
        static::assertSame($source->getContext(), $listing->getContext());
    }

    public function testCreateFromUsesDefaultsForTheListingState(): void
    {
        $listing = ProductListingResult::createFrom($this->createSearchResult());

        static::assertNull($listing->getSorting());
        static::assertSame([], $listing->getCurrentFilters());
        static::assertNull($listing->getStreamId());
    }

    public function testCreateFromKeepsPaginationAggregationsExtensionsAndStates(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(20);

        $source = new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            42,
            new ProductCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );
        $source->addExtension('custom', new ArrayStruct(['foo' => 'bar']));
        $source->addState('custom-state');

        $listing = ProductListingResult::createFrom($source);

        static::assertSame(10, $listing->getLimit());
        static::assertSame(3, $listing->getPage());
        static::assertSame($source->getAggregations(), $listing->getAggregations());
        static::assertSame($source->getExtension('custom'), $listing->getExtension('custom'));
        static::assertTrue($listing->hasState('custom-state'));
    }

    public function testSettersFillTheListingState(): void
    {
        $sortings = new ProductSortingCollection();

        $listing = ProductListingResult::createFrom($this->createSearchResult());
        $listing->setAvailableSortings($sortings);
        $listing->setSorting('name-asc');
        $listing->addCurrentFilter('category', 'electronics');
        $listing->setStreamId('stream-id-1');

        static::assertSame($sortings, $listing->getAvailableSortings());
        static::assertSame('name-asc', $listing->getSorting());
        static::assertSame(['category' => 'electronics'], $listing->getCurrentFilters());
        static::assertSame('stream-id-1', $listing->getStreamId());
    }

    /**
     * @deprecated tag:v6.8.0 - reason: the tested factory is removed with the next major - to be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testFromSearchResultSetsListingSpecificFields(): void
    {
        $sortings = new ProductSortingCollection();

        $listing = ProductListingResult::fromSearchResult(
            $this->createSearchResult(),
            availableSortings: $sortings,
            sorting: 'name-asc',
            currentFilters: ['category' => 'electronics'],
            streamId: 'stream-id-1',
        );

        static::assertSame($sortings, $listing->getAvailableSortings());
        static::assertSame('name-asc', $listing->getSorting());
        static::assertSame(['category' => 'electronics'], $listing->getCurrentFilters());
        static::assertSame('stream-id-1', $listing->getStreamId());
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        $entities = new ProductCollection();

        return new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
