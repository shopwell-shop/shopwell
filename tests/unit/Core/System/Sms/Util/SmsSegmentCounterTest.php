<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Sms\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Util\SmsSegmentCounter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SmsSegmentCounter::class)]
class SmsSegmentCounterTest extends TestCase
{
    public function testItCountsTheSegmentsOfTheGsmAlphabet(): void
    {
        $counter = new SmsSegmentCounter();

        static::assertSame(0, $counter->count(''));
        static::assertSame(1, $counter->count('a'));
        static::assertSame(1, $counter->count(str_repeat('a', 160)));
        static::assertSame(2, $counter->count(str_repeat('a', 161)));
        static::assertSame(3, $counter->count(str_repeat('a', 400)));
    }

    public function testItCountsTheSegmentsOfAMessageOutsideTheGsmAlphabet(): void
    {
        $counter = new SmsSegmentCounter();

        static::assertSame(1, $counter->count(str_repeat('中', 70)));
        static::assertSame(2, $counter->count(str_repeat('中', 71)));
    }
}
