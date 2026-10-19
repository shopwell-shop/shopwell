<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Util\CountryDialCodeResolver;
use Shopwell\Core\System\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\System\Sms\Util\ShippedCountryDialCodeProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PhoneNumberNormalizer::class)]
class PhoneNumberNormalizerTest extends TestCase
{
    public function testKeepsANumberThatIsAlreadyInternational(): void
    {
        $number = $this->createNormalizer()->normalize('+8613800000000');

        static::assertSame('+8613800000000', $number->e164());
        static::assertSame('CN', $number->countryIso());
    }

    public function testStripsTheSeparators(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->normalize('+86 138-0000.0000')->e164());
        static::assertSame('+8613800000000', $this->createNormalizer()->normalize('+86 (138) 0000/0000')->e164());
    }

    public function testDoubleZeroPrefixIsRewrittenToPlus(): void
    {
        $number = $this->createNormalizer()->normalize('004915112345678');

        static::assertSame('+4915112345678', $number->e164());
        static::assertSame('DE', $number->countryIso());
    }

    public function testInternationalNumberOfUnlistedCountryIsAccepted(): void
    {
        $number = $this->createNormalizer()->normalize('+998901234567');

        // the dial code data does not list 998, which is a normal state and not an error
        static::assertSame('+998901234567', $number->e164());
        static::assertNull($number->countryIso());
        static::assertSame('998901234567', $number->nationalNumber());
    }

    public function testNationalNumberWithoutCountryIsRejectedExplicitly(): void
    {
        // the module no longer guesses a country, so no +86 is glued in front of a number of Uzbekistan
        $this->expectExceptionObject(SmsException::phoneNumberNotInternational('998901234567'));

        $this->createNormalizer()->normalize('998901234567');
    }

    public function testNationalNumberIsCanonicalisedAgainstTheCountryOfTheCaller(): void
    {
        $number = $this->createNormalizer()->normalize('4155552671', 'US');

        static::assertSame('+14155552671', $number->e164());
        static::assertSame('US', $number->countryIso());
    }

    public function testTheTrunkPrefixOfANationalNumberIsStripped(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->normalize('0138-0000-0000', 'CN')->e164());
        static::assertSame('+493012345678', $this->createNormalizer()->normalize('03012345678', 'de')->e164());
    }

    public function testTheDialCodeANationalNumberAlreadyCarriesIsNotDoubled(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->normalize('8613800000000', 'CN')->e164());
    }

    public function testNormalisationNeverAddsADigitPrefixThatWasNotInTheInput(): void
    {
        $normalizer = $this->createNormalizer();

        foreach (['+8613800000000', '+14155552671', '+998901234567', '004915112345678'] as $written) {
            $number = $normalizer->normalize($written);
            $digits = ltrim($written, '+');
            $digits = str_starts_with($written, '00') ? substr($written, 2) : $digits;

            static::assertStringStartsWith('+', $number->e164());
            static::assertSame($digits, substr($number->e164(), 1));
        }
    }

    public function testACountryTheDataDoesNotKnowCannotPlaceANationalNumber(): void
    {
        $this->expectExceptionObject(SmsException::phoneNumberNotInternational('901234567'));

        $this->createNormalizer()->normalize('901234567', 'UZ');
    }

    public function testRejectsAnEmptyNumber(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('   '));

        $this->createNormalizer()->normalize('   ');
    }

    public function testEmptyAndAllZeroNumbersAreRejected(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('00000000'));

        $this->createNormalizer()->normalize('00000000', 'CN');
    }

    public function testRejectsANumberThatIsTooShort(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+1234'));

        $this->createNormalizer()->normalize('+1234');
    }

    public function testRejectsANumberThatIsTooLong(): void
    {
        $number = '+1234567890123456';

        $this->expectExceptionObject(SmsException::invalidPhoneNumber($number));

        $this->createNormalizer()->normalize($number);
    }

    public function testRejectsANumberThatIsNotMadeOfDigits(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+861380000000a'));

        $this->createNormalizer()->normalize('+861380000000a');
    }

    private function createNormalizer(): PhoneNumberNormalizer
    {
        return new PhoneNumberNormalizer(new CountryDialCodeResolver([new ShippedCountryDialCodeProvider()]));
    }
}
