<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductAvailableFilter::class)]
class ProductAvailableFilterTest extends TestCase
{
    public function testCreatesProductAvailableFilter(): void
    {
        $salesChannelId = TestDefaults::SALES_CHANNEL;
        $visibility = ProductVisibilityDefinition::VISIBILITY_ALL;

        $filter = new ProductAvailableFilter($salesChannelId, $visibility);

        static::assertSame($salesChannelId, $filter->getSalesChannelId());
        static::assertSame($visibility, $filter->getVisibility());
        static::assertCount(3, $filter->getQueries());
        static::assertSame([
            (new RangeFilter('product.visibilities.visibility', [RangeFilter::GTE => $visibility]))->jsonSerialize(),
            (new EqualsFilter('product.visibilities.salesChannelId', $salesChannelId))->jsonSerialize(),
            (new EqualsFilter('product.active', true))->jsonSerialize(),
        ], array_map(
            static fn (Filter $filter): array => $filter->jsonSerialize(),
            $filter->getQueries()
        ));
    }
}
