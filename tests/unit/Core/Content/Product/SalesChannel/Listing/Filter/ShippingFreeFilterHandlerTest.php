<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopwell\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ShippingFreeListingFilterHandler::class)]
class ShippingFreeFilterHandlerTest extends TestCase
{
    public function testFilterCanBeSkipped(): void
    {
        $result = (new ShippingFreeListingFilterHandler())->create(
            new Request([], ['shipping-free-filter' => false]),
            static::createStub(SalesChannelContext::class)
        );

        static::assertNull($result);
    }

    #[DataProvider('filterProvider')]
    public function testFilterCanBeCreated(bool $input): void
    {
        $result = (new ShippingFreeListingFilterHandler())->create(
            new Request(['shipping-free' => $input]),
            static::createStub(SalesChannelContext::class)
        );

        $expected = new Filter(
            'shipping-free',
            $input,
            [
                new FilterAggregation(
                    'shipping-free-filter',
                    new MaxAggregation('shipping-free', 'product.shippingFree'),
                    [new EqualsFilter('product.shippingFree', true)]
                ),
            ],
            new EqualsFilter('product.shippingFree', true),
            $input
        );

        static::assertEquals($expected, $result);
    }

    public static function filterProvider(): \Generator
    {
        yield 'shipping free' => [true];
        yield 'not shipping free' => [false];
    }
}
