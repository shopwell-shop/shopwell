<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Product\Hook\Pricing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Facade\PriceFacade;
use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Checkout\Cart\Price\CashRounding;
use Shopwell\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Shopwell\Core\Content\Product\Hook\Pricing\CheapestPriceFacade;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheapestPriceFacade::class)]
class CheapestPriceFacadeTest extends TestCase
{
    #[DataProvider('providerChange')]
    public function testChange(string $currencyKey, string $taxState, float $unit, float $tax): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, $currencyKey, $taxState);

        $update = new PriceCollection([
            new Price(Defaults::CURRENCY, 2, 5, false),
            new Price($ids->get('usd'), 1, 4, false),
        ]);

        $price->change($update);

        static::assertSame($unit, $price->getUnit());
        static::assertSame($tax, $price->getTaxes()->getAmount());
    }

    public function testChangeWithPriceFacade(): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, 'default', CartPrice::TAX_STATE_GROSS);

        $price->change(
            new PriceFacade(
                new Entity(),
                new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection()),
                static::createStub(ScriptPriceStubs::class),
                static::createStub(SalesChannelContext::class)
            )
        );

        static::assertSame(5.0, $price->getUnit());
    }

    public function testChangeWithNullFacade(): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, 'default', CartPrice::TAX_STATE_GROSS);

        $price->change(null);

        static::assertSame(10.0, $price->getUnit());
    }

    public function testReset(): void
    {
        $ids = new IdsCollection([
            'default' => Defaults::CURRENCY,
            'usd' => Uuid::randomHex(),
        ]);

        $price = $this->rampUpPriceFacade($ids, 'default', CartPrice::TAX_STATE_GROSS);

        $price->reset();

        static::assertSame(10.0, $price->getUnit());
    }

    public static function providerChange(): \Generator
    {
        yield 'Test default currency' => ['default', CartPrice::TAX_STATE_GROSS, 5.0, 0.45];
        yield 'Test usd currency' => ['usd', CartPrice::TAX_STATE_GROSS, 4.0, 0.36];

        yield 'Test net default currency' => ['default', CartPrice::TAX_STATE_NET, 2.0, 0.2];
        yield 'Test net usd currency' => ['usd', CartPrice::TAX_STATE_NET, 1.0, 0.1];
    }

    private function rampUpPriceFacade(IdsCollection $ids, string $currencyKey, string $taxState): CheapestPriceFacade
    {
        $entity = new class extends Entity {
            protected CalculatedPrice $calculatedPrice;
        };

        $quantityCalculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        );

        $stubs = new ScriptPriceStubs(
            // not necessary for this test
            static::createStub(Connection::class),
            $quantityCalculator,
            new PercentagePriceCalculator(new CashRounding(), $quantityCalculator, new PercentageTaxRuleBuilder()),
        );

        $entity->assign(['calculatedPrice' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())]);

        $original = new CalculatedCheapestPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(new TaxRuleCollection([new TaxRule(10)])));

        // mock context to simulate currency and tax states
        $context = static::createStub(SalesChannelContext::class);

        // currency key will be provided, we want to test different currencies are taking into account
        $context->method('getCurrencyId')->willReturn($ids->get($currencyKey));

        // we also want to test different tax states (gross/net)
        $context->method('getTaxState')->willReturn($taxState);
        $context->method('getItemRounding')->willReturn(new CashRoundingConfig(2, 0.01, true));

        return new CheapestPriceFacade($entity, $original, $stubs, $context);
    }
}
