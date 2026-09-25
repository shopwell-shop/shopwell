<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\Processor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Processor\ContainerCartProcessor;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\AbsoluteItem;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\CalculatedTaxes;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\ContainerItem;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\HighTaxes;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\LowTaxes;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\PercentageItem;
use Shopwell\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures\QuantityItem;

/**
 * @internal
 */
#[Package('checkout')]
class ContainerCartProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('calculationProvider')]
    public function testCalculation(LineItem $item, ?CalculatedPrice $expected): void
    {
        $processor = static::getContainer()->get(ContainerCartProcessor::class);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection([$item]));

        $new = new Cart('after');
        $processor->process(new CartDataCollection(), $cart, $new, $context, new CartBehavior());

        if ($expected === null) {
            static::assertFalse($new->has($item->getId()));

            return;
        }

        static::assertTrue($new->has($item->getId()));

        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice());
        static::assertSame($expected->getUnitPrice(), $item->getPrice()->getUnitPrice());
        static::assertSame($expected->getTotalPrice(), $item->getPrice()->getTotalPrice());
        static::assertSame($expected->getCalculatedTaxes()->getAmount(), $item->getPrice()->getCalculatedTaxes()->getAmount());

        foreach ($expected->getCalculatedTaxes() as $tax) {
            $actual = $item->getPrice()->getCalculatedTaxes()->get((string) $tax->getTaxRate());

            static::assertInstanceOf(CalculatedTax::class, $actual, \sprintf('Missing tax for rate %F', $tax->getTaxRate()));
            static::assertSame($tax->getTax(), $actual->getTax());
        }

        foreach ($item->getPrice()->getCalculatedTaxes() as $tax) {
            $actual = $expected->getCalculatedTaxes()->get((string) $tax->getTaxRate());

            static::assertInstanceOf(CalculatedTax::class, $actual, \sprintf('Missing tax for rate %F', $tax->getTaxRate()));
            static::assertSame($tax->getTax(), $actual->getTax());
        }
    }

    public static function calculationProvider(): \Generator
    {
        yield 'Test empty container will be removed' => [
            new ContainerItem(),
            null,
        ];

        yield 'Test container with one quantity price definition' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
            ]),
            new CalculatedPrice(20, 20, new CalculatedTaxes([19 => 3.19]), new HighTaxes()),
        ];

        yield 'Test percentage discount for one item' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
                new PercentageItem(-10),
            ]),
            new CalculatedPrice(18, 18, new CalculatedTaxes([19 => 2.87]), new HighTaxes()),
        ];

        yield 'Test absolute discount for one item' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
                new AbsoluteItem(-10),
            ]),
            new CalculatedPrice(10, 10, new CalculatedTaxes([19 => 1.59]), new HighTaxes()),
        ];

        yield 'Test discount calculation for two items' => [
            new ContainerItem([
                new QuantityItem(20, new HighTaxes()),
                new QuantityItem(20, new LowTaxes()),
                new PercentageItem(-10),
            ]),
            new CalculatedPrice(36, 36, new CalculatedTaxes([19 => 2.87, 7 => 1.18]), new HighTaxes()),
        ];

        yield 'Test discount calculation with random order' => [
            new ContainerItem([
                new QuantityItem(20, new LowTaxes()),
                new PercentageItem(-10),
                new QuantityItem(20, new HighTaxes()),
            ]),
            new CalculatedPrice(36, 36, new CalculatedTaxes([19 => 2.87, 7 => 1.18]), new HighTaxes()),
        ];

        yield 'Test nested calculation' => [
            new ContainerItem([ // 108,40€ - 10% = 97,56€
                new QuantityItem(20, new HighTaxes()),
                new QuantityItem(20, new LowTaxes()),
                new PercentageItem(-10),

                new ContainerItem([ // 76€ - 10% = 68,40€
                    new QuantityItem(20, new HighTaxes()),
                    new QuantityItem(20, new LowTaxes()),

                    new ContainerItem([                             // 40 - 10% = 36€
                        new QuantityItem(20, new HighTaxes()),
                        new QuantityItem(20, new LowTaxes()),
                        new PercentageItem(-10),
                    ]),
                    new PercentageItem(-10),
                ]),
            ]),
            // v6.8: PercentagePriceCalculator scales and rounds each calculated tax instead of recalculating
            new CalculatedPrice(97.56, 97.56, new CalculatedTaxes(Feature::isActive('v6.8.0.0') ? [19 => 7.78, 7 => 3.19] : [19 => 7.77, 7 => 3.20]), new HighTaxes()),
        ];
    }
}
