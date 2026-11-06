<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;

/**
 * The number range generator of a test: it answers the number the range would hand out, and the
 * service under test must never build a number of its own again.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
final class NumberRangeFixture extends TestCase
{
    public static function generator(string $value): AbstractNumberRangeValueGenerator
    {
        $generator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $generator->method('getValue')->willReturn($value);

        return $generator;
    }
}
