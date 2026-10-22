<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Util\PhoneNumber;
use Shopwell\Core\System\Sms\Util\PhoneNumberMasker;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PhoneNumberMasker::class)]
class PhoneNumberMaskerTest extends TestCase
{
    public function testItMasksTheMiddleOfTheNationalPart(): void
    {
        $masker = new PhoneNumberMasker();

        static::assertSame('+86138****0000', $masker->mask(PhoneNumber::fromE164('+8613800000000', 'CN', '86')));
        static::assertSame('+1415****2671', $masker->mask(PhoneNumber::fromE164('+14155552671', 'US', '1')));
        static::assertSame('+852123****5678', $masker->mask(PhoneNumber::fromE164('+85212345678', 'HK', '852')));
    }

    public function testItMasksTheTailOfANumberWhoseCountryIsUnknown(): void
    {
        // the dial code is unknown, so the masker cannot tell where the head of the number ends
        static::assertSame('+998****4567', (new PhoneNumberMasker())->mask(PhoneNumber::fromE164('+998901234567', null, '')));
    }

    public function testItMasksTheTailWhenTheNationalPartIsTooShort(): void
    {
        // a national part of six digits has no middle that could be hidden
        static::assertSame('+123****4567', (new PhoneNumberMasker())->mask(PhoneNumber::fromE164('+1234567', 'US', '1')));
    }
}
