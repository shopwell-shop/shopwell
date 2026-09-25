<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopwell\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\CashRounding;
use Shopwell\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountFixedPriceCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DiscountFixedPriceCalculator::class)]
class DiscountFixedPriceCalculatorTest extends TestCase
{
    #[DataProvider('priceProvider')]
    public function testCalculate(float $fixedPrice, float $packageSum, float $expectedDiscount): void
    {
        $context = Generator::generateSalesChannelContext();
        $discountCalculator = new DiscountFixedPriceCalculator($this->createAbsolutePriceCalculator());
        $discount = new DiscountLineItem(
            'fixed price promotion',
            new AbsolutePriceDefinition($fixedPrice),
            ['discountScope' => 'cart', 'discountType' => 'fixed'],
            null
        );

        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex());
        $lineItem->setPrice(new CalculatedPrice($packageSum, $packageSum, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $package = new DiscountPackage(new LineItemQuantityCollection([
            new LineItemQuantity($lineItem->getId(), 1),
        ]));
        $package->setCartItems(new LineItemFlatCollection([$lineItem]));

        $result = $discountCalculator->calculate($discount, new DiscountPackageCollection([$package]), $context);

        static::assertSame($expectedDiscount, $result->getPrice()->getTotalPrice());
    }

    /**
     * @return iterable<string, float[]>
     */
    public static function priceProvider(): iterable
    {
        yield 'configured fixed price is lower than cart total: applies the remaining discount' => [40.00, 60.00, -20.00];
        yield 'configured fixed price matches cart total: applies no discount' => [40.00, 40.00, 0.00];
        yield 'configured fixed price exceeds cart total: applies no discount' => [40.00, 19.99, 0.00];
    }

    private function createAbsolutePriceCalculator(): AbsolutePriceCalculator
    {
        $rounding = new CashRounding();
        $taxCalculator = new TaxCalculator();

        return new AbsolutePriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding),
                new NetPriceCalculator($taxCalculator, $rounding),
            ),
            new PercentageTaxRuleBuilder()
        );
    }
}
