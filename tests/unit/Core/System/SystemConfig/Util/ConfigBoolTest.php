<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\SystemConfig\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SystemConfig\Util\ConfigBool;

/**
 * `system:config:set` keeps the raw argument unless `--json` is passed, so a shop that runs
 * `system:config:set core.wallet.enabled false` stores the string "false" - which a plain
 * `(bool)` cast reads as enabled. These are the spellings that have to count as "off".
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigBool::class)]
class ConfigBoolTest extends TestCase
{
    public static function stringSpellingsOfOff(): \Generator
    {
        yield 'false' => ['false'];
        yield 'zero' => ['0'];
        yield 'empty' => [''];
        yield 'off' => ['off'];
        yield 'no' => ['no'];
        yield 'surrounding spaces' => ['  false  '];
        yield 'uppercase' => ['FALSE'];
        yield 'mixed case' => ['Off'];
    }

    #[DataProvider('stringSpellingsOfOff')]
    public function testEverySpellingOfOffIsOff(string $value): void
    {
        static::assertFalse(ConfigBool::normalize($value));
    }

    public static function stringSpellingsOfOn(): \Generator
    {
        yield 'true' => ['true'];
        yield 'one' => ['1'];
        yield 'on' => ['on'];
        yield 'yes' => ['yes'];
        yield 'any other word' => ['whatever'];
        yield 'surrounding spaces' => [' true '];
    }

    #[DataProvider('stringSpellingsOfOn')]
    public function testEveryOtherStringIsOn(string $value): void
    {
        static::assertTrue(ConfigBool::normalize($value));
    }

    public function testNativeTypesKeepTheirCast(): void
    {
        static::assertTrue(ConfigBool::normalize(true));
        static::assertFalse(ConfigBool::normalize(false));
        static::assertTrue(ConfigBool::normalize(1));
        static::assertFalse(ConfigBool::normalize(0));
        static::assertTrue(ConfigBool::normalize(0.5));
        static::assertFalse(ConfigBool::normalize(0.0));
    }
}
