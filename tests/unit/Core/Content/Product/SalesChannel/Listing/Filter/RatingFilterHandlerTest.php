<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\RatingListingFilterHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(RatingListingFilterHandler::class)]
class RatingFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new RatingListingFilterHandler())->create(
            new Request([], ['rating-filter' => false]),
            static::createStub(SalesChannelContext::class)
        );

        static::assertNull($result);
    }

    #[DataProvider('filterProvider')]
    public function testFilterCanBeCreated(int $input): void
    {
        $result = (new RatingListingFilterHandler())->create(
            new Request(['rating' => $input]),
            static::createStub(SalesChannelContext::class)
        );

        $expected = new Filter(
            'rating',
            true,
            [
                new FilterAggregation(
                    'rating-exists',
                    new MaxAggregation('rating', 'product.ratingAverage'),
                    [new RangeFilter('product.ratingAverage', [RangeFilter::GTE => 0])]
                ),
            ],
            new RangeFilter('product.ratingAverage', [
                RangeFilter::GTE => $input,
            ]),
            $input
        );

        static::assertEquals($expected, $result);
    }

    public static function filterProvider(): \Generator
    {
        yield 'rating better than 4' => [4];
        yield 'rating better than 3' => [3];
    }
}
