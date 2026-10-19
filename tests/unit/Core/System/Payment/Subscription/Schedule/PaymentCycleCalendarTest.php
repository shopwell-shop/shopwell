<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription\Schedule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Subscription\Schedule\PaymentCycleCalendar;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCycleCalendar::class)]
class PaymentCycleCalendarTest extends TestCase
{
    private const SHANGHAI = 'Asia/Shanghai';

    private PaymentCycleCalendar $calendar;

    protected function setUp(): void
    {
        $this->calendar = new PaymentCycleCalendar();
    }

    #[DataProvider('periodEnds')]
    public function testNextPeriodEnd(string $anchor, string $unit, int $count, string $expected): void
    {
        $result = $this->calendar->nextPeriodEnd($this->shanghai($anchor), $unit, $count);

        static::assertSame(
            $expected,
            $result->setTimezone(new \DateTimeZone(self::SHANGHAI))->format('Y-m-d H:i:s')
        );
        static::assertSame(self::SHANGHAI, $result->getTimezone()->getName());
    }

    /**
     * The anchor of the next call is the result of the previous one: the calendar must advance
     * period by period, never recompute from the original signing date. A plan signed on Jan 31st
     * pays for Feb 1st-28th, then Mar 1st-28th - the clipped February end is a real period end,
     * and March is the (shorter) period that follows it.
     */
    public function testPeriodEndsChainPeriodByPeriodAfterAClampedFebruary(): void
    {
        $calendar = new PaymentCycleCalendar();
        $anchor = new \DateTimeImmutable('2025-01-31', new \DateTimeZone(self::SHANGHAI));
        $februaryEnd = $calendar->nextPeriodEnd($anchor, PaymentCycleCalendar::UNIT_MONTH, 1);
        $marchEnd = $calendar->nextPeriodEnd($februaryEnd, PaymentCycleCalendar::UNIT_MONTH, 1);

        static::assertSame('2025-02-28', $februaryEnd->format('Y-m-d'));
        static::assertSame('2025-03-28', $marchEnd->format('Y-m-d'));
    }

    public function testZeroCountIsRejected(): void
    {
        $this->expectException(PaymentException::class);

        $this->calendar->nextPeriodEnd(new \DateTimeImmutable('2025-01-31'), PaymentCycleCalendar::UNIT_MONTH, 0);
    }

    public function testUnknownUnitIsRejected(): void
    {
        $this->expectException(PaymentException::class);

        $this->calendar->nextPeriodEnd(new \DateTimeImmutable('2025-01-31'), 'decade', 1);
    }

    #[DataProvider('clamps')]
    public function testClampIntoWindow(string $at, string $expected): void
    {
        $result = $this->calendar->clampIntoWindow($this->shanghai($at));

        static::assertSame($expected, $result->setTimezone(new \DateTimeZone(self::SHANGHAI))->format('Y-m-d H:i:s'));
    }

    #[DataProvider('windowMembership')]
    public function testIsWithinWindow(string $at, bool $expected): void
    {
        static::assertSame($expected, $this->calendar->isWithinWindow($this->shanghai($at)));
    }

    public function testDeductionWindowStartIsSevenLocal(): void
    {
        $result = $this->calendar->deductionWindowStart($this->shanghai('2025-01-31 15:42:10'));

        static::assertSame('2025-01-31 07:00:00', $result->format('Y-m-d H:i:s'));
        static::assertSame(self::SHANGHAI, $result->getTimezone()->getName());
    }

    #[DataProvider('earliestExecutable')]
    public function testEarliestExecutableAt(string $now, string $expected): void
    {
        $result = $this->calendar->earliestExecutableAt($this->shanghai($now));

        static::assertSame($expected, $result->setTimezone(new \DateTimeZone(self::SHANGHAI))->format('Y-m-d H:i:s'));
    }

    public function testAlipayRejectsMonthlyAnchorAfterThe28th(): void
    {
        $this->expectException(PaymentException::class);

        $this->calendar->assertExpressibleBy('alipay', PaymentCycleCalendar::UNIT_MONTH, 1, 29);
    }

    public function testAlipayAcceptsMonthlyAnchorOnThe28th(): void
    {
        $this->calendar->assertExpressibleBy('alipay', PaymentCycleCalendar::UNIT_MONTH, 1, 28);
        $this->calendar->assertExpressibleBy('alipay', PaymentCycleCalendar::UNIT_MONTH, 3, 1);

        $this->addToAssertionCount(1);
    }

    public function testAlipayAcceptsNonMonthlyPeriodsWithAnyAnchorDay(): void
    {
        $this->calendar->assertExpressibleBy('alipay', PaymentCycleCalendar::UNIT_WEEK, 1, 31);
        $this->calendar->assertExpressibleBy('alipay', PaymentCycleCalendar::UNIT_YEAR, 1, 31);

        $this->addToAssertionCount(1);
    }

    public function testWechatAcceptsMonthlyAnchorsAlipayCannotExpress(): void
    {
        $this->calendar->assertExpressibleBy('wechat', PaymentCycleCalendar::UNIT_MONTH, 1, 31);

        $this->addToAssertionCount(1);
    }

    #[RunInSeparateProcess]
    public function testResultsAreIndependentOfTheServerTimezone(): void
    {
        $previous = \date_default_timezone_get();
        \date_default_timezone_set('UTC');

        try {
            $februaryEnd = $this->calendar->nextPeriodEnd(
                new \DateTimeImmutable('2025-01-31 12:30:00'),
                PaymentCycleCalendar::UNIT_MONTH,
                1
            );

            static::assertSame('2025-02-28 00:00:00', $februaryEnd->format('Y-m-d H:i:s'));
            static::assertSame(self::SHANGHAI, $februaryEnd->getTimezone()->getName());

            // 23:30 UTC is 07:30 the next day in Beijing - inside the window, but the clamp must
            // still decide against Beijing time, not against the server clock. Within the window
            // the clamp returns the instant untouched.
            static::assertTrue($this->calendar->isWithinWindow(new \DateTimeImmutable('2025-06-10 23:30:00')));
            static::assertSame(
                '2025-06-11 07:30:00',
                $this->calendar->clampIntoWindow(new \DateTimeImmutable('2025-06-10 23:30:00'))
                    ->setTimezone(new \DateTimeZone(self::SHANGHAI))->format('Y-m-d H:i:s')
            );
        } finally {
            \date_default_timezone_set($previous);
        }
    }

    /**
     * @return \Generator<string, array{string, string, int, string}>
     */
    public static function periodEnds(): \Generator
    {
        yield 'jan 31 + 1 month, common year clips to feb 28' => [
            '2025-01-31 16:00:00', PaymentCycleCalendar::UNIT_MONTH, 1, '2025-02-28 00:00:00',
        ];
        yield 'jan 31 + 1 month, leap year keeps feb 29' => [
            '2024-01-31 16:00:00', PaymentCycleCalendar::UNIT_MONTH, 1, '2024-02-29 00:00:00',
        ];
        yield 'mar 31 + 1 month clips to apr 30' => [
            '2025-03-31', PaymentCycleCalendar::UNIT_MONTH, 1, '2025-04-30 00:00:00',
        ];
        yield 'may 31 + 1 month clips to jun 30' => [
            '2025-05-31', PaymentCycleCalendar::UNIT_MONTH, 1, '2025-06-30 00:00:00',
        ];
        yield 'feb 29 + 1 year clips to feb 28' => [
            '2024-02-29', PaymentCycleCalendar::UNIT_YEAR, 1, '2025-02-28 00:00:00',
        ];
        yield 'december + 1 month crosses into the next year' => [
            '2025-12-15', PaymentCycleCalendar::UNIT_MONTH, 1, '2026-01-15 00:00:00',
        ];
        yield 'two months from jan 31 step through the clipped february' => [
            '2025-01-31', PaymentCycleCalendar::UNIT_MONTH, 2, '2025-03-28 00:00:00',
        ];
        yield 'daily period crosses a month boundary' => [
            '2025-01-30', PaymentCycleCalendar::UNIT_DAY, 5, '2025-02-04 00:00:00',
        ];
        yield 'weekly period crosses a month boundary' => [
            '2025-01-28', PaymentCycleCalendar::UNIT_WEEK, 1, '2025-02-04 00:00:00',
        ];
        yield 'weekly count 3 crosses a month boundary' => [
            '2025-01-20', PaymentCycleCalendar::UNIT_WEEK, 3, '2025-02-10 00:00:00',
        ];
        yield 'unclipped months pass through untouched' => [
            '2025-04-15', PaymentCycleCalendar::UNIT_MONTH, 3, '2025-07-15 00:00:00',
        ];
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function clamps(): \Generator
    {
        yield 'one minute before the window moves to its start' => [
            '2025-06-10 06:59:00', '2025-06-10 07:00:00',
        ];
        yield 'the window start itself is kept' => [
            '2025-06-10 07:00:00', '2025-06-10 07:00:00',
        ];
        yield 'one minute before the window end is kept' => [
            '2025-06-10 21:59:59', '2025-06-10 21:59:59',
        ];
        yield 'the window end itself rolls into the next morning' => [
            '2025-06-10 22:00:00', '2025-06-11 07:00:00',
        ];
        yield 'late evening rolls into the next morning' => [
            '2025-06-10 23:00:00', '2025-06-11 07:00:00',
        ];
        yield 'just before midnight rolls into the next morning' => [
            '2025-06-10 23:59:59', '2025-06-11 07:00:00',
        ];
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function windowMembership(): \Generator
    {
        yield '07:00:00 belongs to the window' => ['2025-06-10 07:00:00', true];
        yield 'midday belongs to the window' => ['2025-06-10 12:00:00', true];
        yield '21:59:59 belongs to the window' => ['2025-06-10 21:59:59', true];
        yield '22:00:00 is already outside - the window is left-closed, right-open' => ['2025-06-10 22:00:00', false];
        yield '06:59:59 is outside' => ['2025-06-10 06:59:59', false];
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function earliestExecutable(): \Generator
    {
        yield '48h lead time landing inside the window passes through' => [
            '2025-06-01 10:00:00', '2025-06-03 10:00:00',
        ];
        yield '48h lead time landing at night is pushed to the next morning' => [
            '2025-06-01 23:00:00', '2025-06-04 07:00:00',
        ];
        yield '48h lead time landing before the window moves to its start' => [
            '2025-06-01 01:00:00', '2025-06-03 07:00:00',
        ];
    }

    private function shanghai(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone(self::SHANGHAI));
    }
}
