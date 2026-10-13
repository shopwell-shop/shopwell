<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\SmsException;
use Shopwell\Core\Framework\Sms\Util\PhoneNumberNormalizer;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PhoneNumberNormalizer::class)]
class PhoneNumberNormalizerTest extends TestCase
{
    public function testKeepsANumberThatIsAlreadyInternational(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->toE164('+8613800000000'));
    }

    public function testPrefixesTheDefaultCountryCodeOfANationalNumber(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->toE164('13800000000'));
    }

    public function testStripsTheTrunkPrefixAndTheSeparators(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->toE164('0138-0000-0000'));
        static::assertSame('+8613800000000', $this->createNormalizer()->toE164('138 0000 0000'));
    }

    public function testKeepsTheDialCodeANationalNumberAlreadyCarries(): void
    {
        static::assertSame('+8613800000000', $this->createNormalizer()->toE164('8613800000000'));
    }

    public function testAcceptsTheDoubleZeroNotation(): void
    {
        static::assertSame('+44155552671', $this->createNormalizer()->toE164('0044155552671'));
    }

    public function testUsesTheDefaultCountryOfTheConfiguration(): void
    {
        $normalizer = $this->createNormalizer(['core.smsSettings.defaultCountry' => 'US']);

        static::assertSame('+14155552671', $normalizer->toE164('4155552671'));
    }

    public function testUsesTheDefaultCountryOfASalesChannel(): void
    {
        $normalizer = $this->createNormalizer([
            'core.smsSettings.defaultCountry' => 'CN',
            'sales-channel-id' => ['core.smsSettings.defaultCountry' => 'DE'],
        ]);

        static::assertSame('+493012345678', $normalizer->toE164('03012345678', 'sales-channel-id'));
    }

    public function testRejectsAnEmptyNumber(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('   '));

        $this->createNormalizer()->toE164('   ');
    }

    public function testRejectsANumberThatIsTooShort(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('1234'));

        $this->createNormalizer()->toE164('1234');
    }

    public function testRejectsANumberThatIsTooLong(): void
    {
        $number = '1234567890123456';

        $this->expectExceptionObject(SmsException::invalidPhoneNumber($number));

        $this->createNormalizer()->toE164($number);
    }

    public function testRejectsANumberThatIsNotMadeOfDigits(): void
    {
        $this->expectExceptionObject(SmsException::invalidPhoneNumber('+861380000000a'));

        $this->createNormalizer()->toE164('+861380000000a');
    }

    public function testReadsTheCountryOfADialCode(): void
    {
        $normalizer = $this->createNormalizer();

        static::assertSame('CN', $normalizer->countryCode('+8613800000000'));
        static::assertSame('HK', $normalizer->countryCode('+85212345678'));
        static::assertSame('US', $normalizer->countryCode('+14155552671'));
        static::assertSame('RU', $normalizer->countryCode('+74951234567'));
        static::assertNull($normalizer->countryCode('+9991234567'));
    }

    public function testMasksTheMiddleOfALongNumber(): void
    {
        static::assertSame('+86138****0000', $this->createNormalizer()->mask('+8613800000000'));
        static::assertSame('+1415****2671', $this->createNormalizer()->mask('+14155552671'));
    }

    public function testKeepsANumberThatIsTooShortToMask(): void
    {
        static::assertSame('+999123', $this->createNormalizer()->mask('+999123'));
    }

    public function testHashesANumberWithoutKeepingIt(): void
    {
        $normalizer = $this->createNormalizer();

        static::assertSame(Hasher::hash('+8613800000000'), $normalizer->hash('+8613800000000'));
        static::assertNotSame($normalizer->hash('+8613800000000'), $normalizer->hash('+8613800000001'));
    }

    public function testCountsTheSegmentsOfTheGsmAlphabet(): void
    {
        $normalizer = $this->createNormalizer();

        static::assertSame(0, $normalizer->countSegments(''));
        static::assertSame(1, $normalizer->countSegments(str_repeat('a', 160)));
        static::assertSame(2, $normalizer->countSegments(str_repeat('a', 161)));
    }

    public function testCountsTheSegmentsOfAMessageOutsideTheGsmAlphabet(): void
    {
        $normalizer = $this->createNormalizer();

        static::assertSame(1, $normalizer->countSegments(str_repeat('中', 70)));
        static::assertSame(2, $normalizer->countSegments(str_repeat('中', 71)));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createNormalizer(array $config = []): PhoneNumberNormalizer
    {
        return new PhoneNumberNormalizer(new StaticSystemConfigService($config));
    }
}
