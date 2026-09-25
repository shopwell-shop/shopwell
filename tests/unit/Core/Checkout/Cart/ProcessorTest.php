<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartBehavior;
use Shopwell\Core\Checkout\Cart\Price\AmountCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Processor;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopwell\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Shopwell\Core\Checkout\Cart\Validator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\ScriptExecutor;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Processor::class)]
class ProcessorTest extends TestCase
{
    public function testProcessKeepsPersistedStateOfOriginalCart(): void
    {
        $processor = $this->getProcessor();
        $context = Generator::generateSalesChannelContext();

        $cart = new Cart('test');

        $calculated = $processor->process($cart, $context, new CartBehavior());
        static::assertFalse($calculated->isPersisted());

        $cart->setPersisted(true);

        $calculated = $processor->process($cart, $context, new CartBehavior());
        static::assertTrue($calculated->isPersisted());
    }

    private function getProcessor(): Processor
    {
        $amountCalculator = static::createStub(AmountCalculator::class);
        $amountCalculator->method('calculate')->willReturn(
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS)
        );

        $transactionProcessor = static::createStub(TransactionProcessor::class);
        $transactionProcessor->method('process')->willReturn(new TransactionCollection());

        return new Processor(
            static::createStub(Validator::class),
            $amountCalculator,
            $transactionProcessor,
            [],
            [],
            static::createStub(ScriptExecutor::class)
        );
    }
}
