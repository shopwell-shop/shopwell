<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\AbstractPriceSorter;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceAsc;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter\FilterSorterPriceDesc;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(FilterSorterPriceAsc::class)]
#[CoversClass(FilterSorterPriceDesc::class)]
class FilterSorterPriceTest extends TestCase
{
    /**
     * @param array<LineItem> $items
     * @param array<LineItemQuantity> $meta
     * @param array<string> $expected
     */
    #[DataProvider('sortingProvider')]
    public function testSorting(AbstractPriceSorter $sorter, array $meta, array $items, array $expected): void
    {
        $package = new DiscountPackage(new LineItemQuantityCollection($meta));

        $package->setCartItems(new LineItemFlatCollection($items));

        $sorter->sort(new DiscountPackageCollection([$package]));

        $ordered = $package->getMetaData()->fmap(static fn (LineItemQuantity $item) => $item->getLineItemId());

        static::assertSame($expected, $ordered);
    }

    public static function sortingProvider(): \Generator
    {
        yield 'Test ascending sorting' => [
            new FilterSorterPriceAsc(),
            [
                new LineItemQuantity('a', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('c', 1),
            ],
            [
                self::item('a', 200),
                self::item('b', 100),
                self::item('c', 300),
            ],
            ['b', 'a', 'c'],
        ];

        yield 'Test descending sorting' => [
            new FilterSorterPriceDesc(),
            [
                new LineItemQuantity('a', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('c', 1),
            ],
            [
                self::item('a', 200),
                self::item('b', 100),
                self::item('c', 300),
            ],
            ['c', 'a', 'b'],
        ];

        yield 'Test ascending sorting with duplicate meta items' => [
            new FilterSorterPriceAsc(),
            [
                new LineItemQuantity('a', 1),
                new LineItemQuantity('a', 1),
                new LineItemQuantity('a', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('b', 1),
                new LineItemQuantity('c', 1),
                new LineItemQuantity('c', 1),
                new LineItemQuantity('c', 1),
                new LineItemQuantity('c', 1),
            ],
            [
                self::item('a', 200),
                self::item('b', 100),
                self::item('c', 300),
            ],
            ['b', 'b', 'b', 'b', 'b', 'a', 'a', 'a', 'c', 'c', 'c', 'c'],
        ];
    }

    private static function item(string $id, float $price): LineItem
    {
        $item = new LineItem($id, 'product');
        $item->setPrice(new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $item;
    }
}
