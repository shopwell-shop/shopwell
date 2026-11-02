<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Gateway\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\Request\Money;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Money::class)]
class MoneyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function currencyExponentProvider(): iterable
    {
        yield 'default two digits' => ['EUR', 2];
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
        static::assertSame($expected, Money::exponent($currency));
    }

    public function testExponentIsCaseInsensitive(): void
    {
        static::assertSame(0, Money::exponent('jpy'));
        static::assertSame(3, Money::exponent('Bhd'));
    }

    public function testToDecimalFormatsTheMinorUnit(): void
    {
        static::assertSame('12.34', Money::toDecimal(1234, 'CNY'));
    }

    public function testToDecimalKeepsLeadingZeros(): void
    {
        static::assertSame('0.01', Money::toDecimal(1, 'CNY'));
        static::assertSame('0.00', Money::toDecimal(0, 'CNY'));
    }

    public function testToDecimalForCurrencyWithoutMinorUnit(): void
    {
        static::assertSame('500', Money::toDecimal(500, 'JPY'));
    }

    public function testToDecimalForThreeDigitCurrency(): void
    {
        static::assertSame('1.234', Money::toDecimal(1234, 'BHD'));
    }

    public function testToDecimalForNegativeAmount(): void
    {
        static::assertSame('-12.34', Money::toDecimal(-1234, 'CNY'));
        static::assertSame('-500', Money::toDecimal(-500, 'JPY'));
    }

    public function testToMinorUsesTheIntegerPart(): void
    {
        static::assertSame(1200, Money::toMinor('12', 'CNY'));
    }

    public function testToMinorRoundsHalfUp(): void
    {
        static::assertSame(1234, Money::toMinor('12.344', 'CNY'));
        static::assertSame(1235, Money::toMinor('12.345', 'CNY'));
    }

    public function testToMinorRoundsHalfUpForNegativeAmount(): void
    {
        static::assertSame(-1235, Money::toMinor('-12.345', 'CNY'));
    }

    public function testToMinorForCurrencyWithoutMinorUnit(): void
    {
        static::assertSame(12, Money::toMinor('12.4', 'JPY'));
        static::assertSame(13, Money::toMinor('12.5', 'JPY'));
    }

    public function testToMinorForThreeDigitCurrency(): void
    {
        static::assertSame(1234, Money::toMinor('1.2344', 'BHD'));
    }

    public function testToMinorTrimsWhitespace(): void
    {
        static::assertSame(1200, Money::toMinor(' 12 ', 'CNY'));
    }

    public function testToMinorRoundsTheRoundTripBack(): void
    {
        static::assertSame(9999, Money::toMinor(Money::toDecimal(9999, 'CNY'), 'CNY'));
    }

    public function testToMinorThrowsOnInvalidAmount(): void
    {
        $this->expectExceptionObject(PaymentException::invalidAmount(0));

        Money::toMinor('12,34', 'CNY');
    }

    public function testFromFloatFormatsWithTheExponentOfTheCurrency(): void
    {
        static::assertSame(1999, Money::fromFloat(19.99, 'CNY'));
        static::assertSame(19900, Money::fromFloat(199.0, 'CNY'));
    }

    /**
     * The float is formatted before it is converted, so the binary representation of 0.29 - which is
     * slightly below the decimal value - still becomes 29 instead of 28.
     */
    public function testFromFloatDoesNotLeakTheBinaryRepresentation(): void
    {
        static::assertSame(29, Money::fromFloat(0.29, 'CNY'));
        static::assertSame(1150, Money::fromFloat(11.5, 'CNY'));
    }

    public function testFromFloatForCurrencyWithoutMinorUnit(): void
    {
        static::assertSame(13, Money::fromFloat(12.5, 'JPY'));
    }

    public function testToFloatIsTheInverseOfFromFloat(): void
    {
        static::assertSame(19.99, Money::toFloat(1999, 'CNY'));
        static::assertSame(13.0, Money::toFloat(13, 'JPY'));
        static::assertSame(-19.99, Money::toFloat(-1999, 'CNY'));

        foreach ([1, 29, 1999, 123456, 99999999] as $minor) {
            static::assertSame($minor, Money::fromFloat(Money::toFloat($minor, 'CNY'), 'CNY'));
        }
    }

    public function testToFloatForThreeDigitCurrency(): void
    {
        static::assertSame(1.234, Money::toFloat(1234, 'BHD'));
    }
}
