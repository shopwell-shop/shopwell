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
use Shopwell\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountAbsoluteCalculator;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopwell\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopwell\Core\Checkout\Promotion\PromotionException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DiscountAbsoluteCalculator::class)]
class DiscountAbsoluteCalculatorTest extends TestCase
{
    #[DataProvider('priceProvider')]
    public function testCalculate(float $discountIn, float $packageSum, float $discountOut): void
    {
        $context = Generator::generateSalesChannelContext();

        $rounding = new CashRounding();

        $taxCalculator = new TaxCalculator();

        $calculator = new AbsolutePriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding),
                new NetPriceCalculator($taxCalculator, $rounding),
            ),
            new PercentageTaxRuleBuilder()
        );

        $discountCalculator = new DiscountAbsoluteCalculator($calculator);

        $priceDefinition = new AbsolutePriceDefinition($discountIn);
        $discount = new DiscountLineItem('foo', $priceDefinition, ['discountScope' => 'foo', 'discountType' => 'bar'], null);

        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 2);
        $lineItem->setPrice(new CalculatedPrice($packageSum / 2, $packageSum, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $package = new DiscountPackage(
            new LineItemQuantityCollection([
                new LineItemQuantity($lineItem->getId(), 1),
                new LineItemQuantity($lineItem->getId(), 1),
            ])
        );
        $package->setCartItems(new LineItemFlatCollection([$lineItem]));

        $price = $discountCalculator->calculate($discount, new DiscountPackageCollection([$package]), $context);

        static::assertSame($discountOut, $price->getPrice()->getTotalPrice());
    }

    public function testInvalidPriceDefinitionThrow(): void
    {
        $context = Generator::generateSalesChannelContext();

        $rounding = new CashRounding();

        $taxCalculator = new TaxCalculator();

        $calculator = new AbsolutePriceCalculator(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, $rounding),
                new NetPriceCalculator($taxCalculator, $rounding),
            ),
            new PercentageTaxRuleBuilder()
        );

        $discountCalculator = new DiscountAbsoluteCalculator($calculator);

        $priceDefinition = new PercentagePriceDefinition(23.5);
        $discount = new DiscountLineItem('foo', $priceDefinition, ['discountScope' => 'foo', 'discountType' => 'bar'], null);

        static::expectException(PromotionException::class);

        $discountCalculator->calculate($discount, new DiscountPackageCollection(), $context);
    }

    /**
     * @return iterable<string, float[]>
     */
    public static function priceProvider(): iterable
    {
        yield 'discount greater than packages sum' => [100.00, 90.00, -90.00];
        yield 'discount less than packages sum' => [45.00, 90.00, -45.00];
        yield 'discount equal to packages sum' => [45.00, 45.00, -45.00];
        yield 'discount for zero price product' => [0.0, 0.0, 0.0];
    }
}
