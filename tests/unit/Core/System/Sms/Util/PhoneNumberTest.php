<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Util\PhoneNumber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PhoneNumber::class)]
class PhoneNumberTest extends TestCase
{
    public function testItExposesThePartsOfAKnownNumber(): void
    {
        $number = PhoneNumber::fromE164('+8613800000000', 'CN', '86');

        static::assertSame('+8613800000000', $number->e164());
        static::assertSame('CN', $number->countryIso());
        static::assertSame('13800000000', $number->nationalNumber());
        static::assertSame('86', $number->dialCode());
        static::assertSame('8613800000000', $number->withoutPlus());
    }

    public function testANumberOfAnUnknownCountryKeepsTheWholeNumberAsItsNationalPart(): void
    {
        $number = PhoneNumber::fromE164('+998901234567', null, '');

        static::assertNull($number->countryIso());
        static::assertSame('', $number->dialCode());
        static::assertSame('998901234567', $number->nationalNumber());
    }

    public function testItRefusesANumberWithoutThePlus(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('8613800000000'));

        PhoneNumber::fromE164('8613800000000', 'CN', '86');
    }

    public function testItRefusesANumberThatIsNotMadeOfDigits(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+861380000000a'));

        PhoneNumber::fromE164('+861380000000a', 'CN', '86');
    }

    public function testItRefusesANumberThatIsTooShort(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+123456'));

        PhoneNumber::fromE164('+123456', 'US', '1');
    }

    public function testItRefusesANumberThatIsTooLong(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+1234567890123456'));

        PhoneNumber::fromE164('+1234567890123456', 'US', '1');
    }

    public function testItRefusesADialCodeThatIsNoPrefixOfTheNumber(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+8613800000000'));

        PhoneNumber::fromE164('+8613800000000', 'DE', '49');
    }

    public function testItRefusesADialCodeThatIsTheWholeNumber(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+86123456'));

        PhoneNumber::fromE164('+86123456', 'CN', '86123456');
    }

    public function testItRefusesACountryWithoutADialCode(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+998901234567'));

        PhoneNumber::fromE164('+998901234567', 'UZ', '');
    }

    public function testItRefusesACountryThatIsNoIsoCode(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+8613800000000'));

        PhoneNumber::fromE164('+8613800000000', 'China', '86');
    }
}
