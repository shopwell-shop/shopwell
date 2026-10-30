<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Facade;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopwell\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\PriceSelector;
use Shopwell\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ScriptPriceStubs::class)]
class ScriptPriceStubsTest extends TestCase
{
    // fake some static id for the iso
    private const USD_ID = Defaults::LANGUAGE_SYSTEM;

    /**
     * @param array<array-key, array{gross:float, net:float, linked?: bool, currencyId?: string}> $prices
     */
    #[DataProvider('priceCases')]
    public function testPriceFactory(array $prices, PriceCollection $expected): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([
            'USD' => self::USD_ID,
        ]);

        $stubs = new ScriptPriceStubs($connection, static::createStub(QuantityPriceCalculator::class), static::createStub(PercentagePriceCalculator::class), new PriceSelector(new TaxCalculator(), new ExtensionDispatcher(new EventDispatcher())));

        $actual = $stubs->build($prices);

        foreach ($expected as $expectedPrice) {
            $actualPrice = $actual->getCurrencyPrice($expectedPrice->getCurrencyId());

            static::assertInstanceOf(Price::class, $actualPrice);
            static::assertSame($expectedPrice->getNet(), $actualPrice->getNet());
            static::assertSame($expectedPrice->getGross(), $actualPrice->getGross());
            static::assertSame($expectedPrice->getLinked(), $actualPrice->getLinked());
        }
    }

    public function testSelectReturnsTheValueTheCustomerGroupPriceBasisMakesAuthoritative(): void
    {
        $stubs = new ScriptPriceStubs(
            static::createStub(Connection::class),
            static::createStub(QuantityPriceCalculator::class),
            static::createStub(PercentagePriceCalculator::class),
            new PriceSelector(new TaxCalculator(), new ExtensionDispatcher(new EventDispatcher()))
        );

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getTaxState')->willReturn(CartPrice::TAX_STATE_GROSS);
        $context->method('getCurrentCustomerGroup')->willReturn(
            (new CustomerGroupEntity())->assign(['priceBasis' => CustomerGroupEntity::PRICE_BASIS_NET, 'displayGross' => true])
        );

        $selected = $stubs->select(
            new Price(Defaults::CURRENCY, 10.0, 99.99, false),
            new TaxRuleCollection([new TaxRule(19)]),
            $context
        );

        static::assertSame(10.0, $selected->getValue());
        static::assertFalse($selected->isCalculated());
    }

    public static function priceCases(): \Generator
    {
        yield 'manual price definition' => [
            [
                'default' => ['gross' => 100, 'net' => 90],
                'USD' => ['gross' => 90, 'net' => 80],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, false),
                new Price(self::USD_ID, 80, 90, false),
            ]),
        ];

        yield 'storage price definition' => [
            [
                ['gross' => 100, 'net' => 90, 'linked' => true, 'currencyId' => Defaults::CURRENCY],
                ['gross' => 90, 'net' => 80, 'linked' => false, 'currencyId' => self::USD_ID],
            ],
            new PriceCollection([
                new Price(Defaults::CURRENCY, 90, 100, true),
                new Price(self::USD_ID, 80, 90, false),
            ]),
        ];
    }
}
