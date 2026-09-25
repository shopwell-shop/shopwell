<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopwell\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewResult::class)]
class ProductReviewResultTest extends TestCase
{
    public function testFromSearchResultCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $result = ProductReviewResult::fromSearchResult(
            $source,
            matrix: new RatingMatrix([]),
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 5,
        );

        static::assertSame($source->getTotal(), $result->getTotal());
        static::assertSame($source->getEntities(), $result->getEntities());
        static::assertSame($source->getCriteria(), $result->getCriteria());
        static::assertSame($source->getContext(), $result->getContext());
    }

    public function testFromSearchResultSetsReviewSpecificFields(): void
    {
        $matrix = new RatingMatrix([]);
        $customerReview = new ProductReviewEntity();

        $result = ProductReviewResult::fromSearchResult(
            $this->createSearchResult(),
            matrix: $matrix,
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 7,
            customerReview: $customerReview,
            parentId: 'parent-1',
        );

        static::assertSame($matrix, $result->getMatrix());
        static::assertSame('product-1', $result->getProductId());
        static::assertSame(7, $result->getTotalReviewsInCurrentLanguage());
        static::assertSame($customerReview, $result->getCustomerReview());
        static::assertSame('parent-1', $result->getParentId());
    }

    public function testFromSearchResultUsesDefaultsForOptionalExtras(): void
    {
        $result = ProductReviewResult::fromSearchResult(
            $this->createSearchResult(),
            matrix: new RatingMatrix([]),
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 0,
        );

        static::assertNull($result->getCustomerReview());
        static::assertNull($result->getParentId());
    }

    public function testFromSearchResultKeepsPaginationAggregationsExtensionsAndStates(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(20);

        $source = new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            42,
            new ProductReviewCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );
        $source->addExtension('custom', new ArrayStruct(['foo' => 'bar']));
        $source->addState('custom-state');

        $result = ProductReviewResult::fromSearchResult(
            $source,
            matrix: new RatingMatrix([]),
            productId: 'product-1',
            totalReviewsInCurrentLanguage: 0,
        );

        static::assertSame(10, $result->getLimit());
        static::assertSame(3, $result->getPage());
        static::assertSame($source->getAggregations(), $result->getAggregations());
        static::assertSame($source->getExtension('custom'), $result->getExtension('custom'));
        static::assertTrue($result->hasState('custom-state'));
    }

    /**
     * @return EntitySearchResult<ProductReviewCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        $entities = new ProductReviewCollection();

        return new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
