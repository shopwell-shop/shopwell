<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Currency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyAmountConverter;
use Shopwell\Core\System\Currency\CurrencyException;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CurrencyAmountConverter::class)]
class CurrencyAmountConverterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function currencyExponentProvider(): iterable
    {
        yield 'default two digits' => ['EUR', 2];
        yield 'chilean peso has no minor unit' => ['CLP', 0];
        yield 'jordanian dinar has three digits' => ['JOD', 3];
        yield 'uruguayan indexed unit has four digits' => ['UYW', 4];
        yield 'japanese yen has no minor unit' => ['JPY', 0];
        yield 'korean won has no minor unit' => ['KRW', 0];
        yield 'vietnamese dong has no minor unit' => ['VND', 0];
        yield 'bahraini dinar has three digits' => ['BHD', 3];
        yield 'kuwaiti dinar has three digits' => ['KWD', 3];
        yield 'chilean unit of account has four digits' => ['CLF', 4];
    }

    #[DataProvider('currencyExponentProvider')]
    public function testExponentReturnsTheDeclaredExponent(string $currency, int $expected): void
    {
        static::assertSame($expected, CurrencyAmountConverter::fractionDigits($currency));
    }

    public function testExponentIsCaseInsensitive(): void
    {
        static::assertSame(0, CurrencyAmountConverter::fractionDigits('jpy'));
        static::assertSame(3, CurrencyAmountConverter::fractionDigits('Bhd'));
    }

    public function testToDecimalFormatsTheMinorUnit(): void
    {
        static::assertSame('12.34', CurrencyAmountConverter::toDecimal(1234, 'CNY'));
    }

    public function testToDecimalKeepsLeadingZeros(): void
    {
        static::assertSame('0.01', CurrencyAmountConverter::toDecimal(1, 'CNY'));
        static::assertSame('0.00', CurrencyAmountConverter::toDecimal(0, 'CNY'));
    }

    public function testToDecimalForCurrencyWithoutMinorUnit(): void
    {
        static::assertSame('500', CurrencyAmountConverter::toDecimal(500, 'JPY'));
    }

    public function testToDecimalForThreeDigitCurrency(): void
    {
        static::assertSame('1.234', CurrencyAmountConverter::toDecimal(1234, 'BHD'));
    }

    public function testToDecimalForNegativeAmount(): void
    {
        static::assertSame('-12.34', CurrencyAmountConverter::toDecimal(-1234, 'CNY'));
        static::assertSame('-500', CurrencyAmountConverter::toDecimal(-500, 'JPY'));
    }

    public function testToMinorUsesTheIntegerPart(): void
    {
        static::assertSame(1200, CurrencyAmountConverter::toMinor('12', 'CNY'));
    }

    public function testToMinorRoundsHalfUp(): void
    {
        static::assertSame(1234, CurrencyAmountConverter::toMinor('12.344', 'CNY'));
        static::assertSame(1235, CurrencyAmountConverter::toMinor('12.345', 'CNY'));
    }

    public function testToMinorRoundsHalfUpForNegativeAmount(): void
    {
        static::assertSame(-1235, CurrencyAmountConverter::toMinor('-12.345', 'CNY'));
    }

    public function testToMinorForCurrencyWithoutMinorUnit(): void
    {
        static::assertSame(12, CurrencyAmountConverter::toMinor('12.4', 'JPY'));
        static::assertSame(13, CurrencyAmountConverter::toMinor('12.5', 'JPY'));
    }

    public function testToMinorForThreeDigitCurrency(): void
    {
        static::assertSame(1234, CurrencyAmountConverter::toMinor('1.2344', 'BHD'));
    }

    public function testToMinorTrimsWhitespace(): void
    {
        static::assertSame(1200, CurrencyAmountConverter::toMinor(' 12 ', 'CNY'));
    }

    public function testToMinorRoundsTheRoundTripBack(): void
    {
        static::assertSame(9999, CurrencyAmountConverter::toMinor(CurrencyAmountConverter::toDecimal(9999, 'CNY'), 'CNY'));
    }

    public function testToMinorThrowsOnInvalidAmount(): void
    {
        $this->expectExceptionObject(CurrencyException::invalidAmount('12,34', 'CNY'));

        CurrencyAmountConverter::toMinor('12,34', 'CNY');
    }

    public function testFromFloatFormatsWithTheExponentOfTheCurrency(): void
    {
        static::assertSame(1999, CurrencyAmountConverter::fromFloat(19.99, 'CNY'));
        static::assertSame(19900, CurrencyAmountConverter::fromFloat(199.0, 'CNY'));
    }

    /**
     * The float is formatted before it is converted, so the binary representation of 0.29 - which is
     * slightly below the decimal value - still becomes 29 instead of 28.
     */
    public function testFromFloatDoesNotLeakTheBinaryRepresentation(): void
    {
        static::assertSame(29, CurrencyAmountConverter::fromFloat(0.29, 'CNY'));
        static::assertSame(1150, CurrencyAmountConverter::fromFloat(11.5, 'CNY'));
    }

    public function testFromFloatForCurrencyWithoutMinorUnit(): void
    {
        static::assertSame(13, CurrencyAmountConverter::fromFloat(12.5, 'JPY'));
    }

    public function testToFloatIsTheInverseOfFromFloat(): void
    {
        static::assertSame(19.99, CurrencyAmountConverter::toFloat(1999, 'CNY'));
        static::assertSame(13.0, CurrencyAmountConverter::toFloat(13, 'JPY'));
        static::assertSame(-19.99, CurrencyAmountConverter::toFloat(-1999, 'CNY'));

        foreach ([1, 29, 1999, 123456, 99999999] as $minor) {
            static::assertSame($minor, CurrencyAmountConverter::fromFloat(CurrencyAmountConverter::toFloat($minor, 'CNY'), 'CNY'));
        }
    }

    public function testToFloatForThreeDigitCurrency(): void
    {
        static::assertSame(1.234, CurrencyAmountConverter::toFloat(1234, 'BHD'));
    }

    public function testToDecimalPreservesTheMinimumInteger(): void
    {
        static::assertSame('-92233720368547758.08', CurrencyAmountConverter::toDecimal(\PHP_INT_MIN, 'EUR'));
    }

    public function testToMinorRejectsIntegerOverflow(): void
    {
        $this->expectExceptionObject(CurrencyException::invalidAmount('92233720368547758.08', 'EUR'));

        CurrencyAmountConverter::toMinor('92233720368547758.08', 'EUR');
    }

    public function testUnknownCurrencyCannotSilentlyUseTwoDigits(): void
    {
        $this->expectExceptionObject(CurrencyException::unsupportedCurrency('ZZZ'));

        CurrencyAmountConverter::toMinor('1.00', 'ZZZ');
    }

    public function testTheIntegerRangeRoundTripsWithoutFloats(): void
    {
        foreach ([\PHP_INT_MIN, \PHP_INT_MAX] as $amount) {
            static::assertSame($amount, CurrencyAmountConverter::toMinor(CurrencyAmountConverter::toDecimal($amount, 'EUR'), 'EUR'));
        }
    }

    public function testRoundingCannotOverflowTheIntegerRange(): void
    {
        $this->expectExceptionObject(CurrencyException::invalidAmount('92233720368547758.075', 'EUR'));

        CurrencyAmountConverter::toMinor('92233720368547758.075', 'EUR');
    }

    public function testNegativeRoundingCannotOverflowTheIntegerRange(): void
    {
        $this->expectExceptionObject(CurrencyException::invalidAmount('-92233720368547758.085', 'EUR'));

        CurrencyAmountConverter::toMinor('-92233720368547758.085', 'EUR');
    }

    public function testNonFiniteFloatCannotBecomeAPaymentAmount(): void
    {
        $this->expectException(CurrencyException::class);

        CurrencyAmountConverter::fromFloat(\INF, 'EUR');
    }
}
