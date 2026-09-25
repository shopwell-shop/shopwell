<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\CreditCartProcessor;
use Shopwell\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CreditCartProcessor::class)]
class CreditCartProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $data = new CartDataCollection();
        $item = new LineItem('hatoken', 'credit', 'a', 2);
        $item->setPriceDefinition(new AbsolutePriceDefinition(5.0));

        $original = new Cart('original');
        $original->add($item);

        $toCalculate = new Cart('toCalculate');
        $context = Generator::generateSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());

        $calculator = $this->createMock(AbsolutePriceCalculator::class);
        $calculator
            ->expects($this->once())
            ->method('calculate')
            ->with(
                static::equalTo(5.0),
                static::equalTo($toCalculate->getLineItems()->getPrices()),
                static::equalTo($context)
            )
            ->willReturn(new CalculatedPrice(5.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $processor = new CreditCartProcessor($calculator);
        $processor->process($data, $original, $toCalculate, $context, $behavior);

        static::assertCount(1, $toCalculate->getLineItems());
        static::assertSame(10.0, $toCalculate->getLineItems()->first()?->getPrice()?->getTotalPrice());
    }

    public function testNoneCreditItemsIgnored(): void
    {
        $data = new CartDataCollection();
        $item = new LineItem('hatoken', 'product', 'a', 2);
        $item->setPriceDefinition(new AbsolutePriceDefinition(5.0));

        $original = new Cart('original');
        $original->add($item);

        $toCalculate = new Cart('toCalculate');
        $context = Generator::generateSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());

        $calculator = $this->createMock(AbsolutePriceCalculator::class);
        $calculator
            ->expects($this->never())
            ->method('calculate');

        $processor = new CreditCartProcessor($calculator);
        $processor->process($data, $original, $toCalculate, $context, $behavior);

        static::assertCount(0, $toCalculate->getLineItems());
    }

    public function testNonAbsolutePricesIgnored(): void
    {
        $data = new CartDataCollection();
        $item = new LineItem('hatoken', 'product', 'a', 2);
        $item->setPriceDefinition(new QuantityPriceDefinition(5.0, new TaxRuleCollection(), 2));

        $original = new Cart('original');
        $original->add($item);

        $toCalculate = new Cart('toCalculate');
        $context = Generator::generateSalesChannelContext();
        $behavior = new CartBehavior($context->getPermissions());

        $calculator = $this->createMock(AbsolutePriceCalculator::class);
        $calculator
            ->expects($this->never())
            ->method('calculate');

        $processor = new CreditCartProcessor($calculator);
        $processor->process($data, $original, $toCalculate, $context, $behavior);

        static::assertCount(0, $toCalculate->getLineItems());
    }
}
