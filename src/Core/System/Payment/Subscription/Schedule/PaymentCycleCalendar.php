<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription\Schedule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;

/**
 * The deduction calendar for merchant-initiated subscriptions (alipay / wechat).
 *
 * Every wall-clock calculation happens in Asia/Shanghai, no matter what timezone the server
 * happens to run in: both providers schedule their deduction windows against Beijing time and
 * the platform has to agree with them down to the minute.
 *
 * Period arithmetic deliberately advances period by period instead of recomputing from the
 * original anchor. A plan signed on Jan 31st ends its first period on Feb 28th (or 29th) - and
 * the *next* period must then end on Mar 28th, because Feb 28th is the day the calendar actually
 * delivered. Recomputing from the anchor would silently jump back to Mar 31st and hand the
 * subscriber a longer period than they paid for. `nextPeriodEnd()` therefore treats its argument
 * as the previous period end; callers chain it, and `count > 1` applies the same one-period
 * stepping internally so both paths agree.
 */
#[Package('framework')]
final class PaymentCycleCalendar
{
    public const UNIT_DAY = 'day';
    public const UNIT_WEEK = 'week';
    public const UNIT_MONTH = 'month';
    public const UNIT_YEAR = 'year';

    /**
     * Alipay caps `period_rule_params.execute_time` at the 28th of the month, so a monthly plan
     * anchored on the 29th, 30th or 31st simply cannot be expressed there. Refusing loudly beats
     * silently clamping: the subscriber would be charged on a different day than they agreed to.
     */
    public const ALIPAY_MAX_ANCHOR_DAY_OF_MONTH = 28;

    public const WINDOW_START_HOUR = 7;
    public const WINDOW_END_HOUR = 22;

    /**
     * Alipay's new charge flow must be initiated at least 48 hours before the deduction happens.
     */
    public const ALIPAY_LEAD_TIME_HOURS = 48;

    private const TIMEZONE = 'Asia/Shanghai';

    public function nextPeriodEnd(\DateTimeImmutable $anchor, string $unit, int $count): \DateTimeImmutable
    {
        if ($count < 1) {
            throw PaymentException::invalidPeriodCount($count);
        }

        $cursor = $anchor->setTimezone(new \DateTimeZone(self::TIMEZONE))->setTime(0, 0);

        return match ($unit) {
            self::UNIT_DAY => $cursor->modify(\sprintf('+%d days', $count)),
            self::UNIT_WEEK => $cursor->modify(\sprintf('+%d weeks', $count)),
            self::UNIT_MONTH => $this->addPeriods($cursor, $count, 1),
            self::UNIT_YEAR => $this->addPeriods($cursor, $count, 12),
            default => throw PaymentException::invalidPeriodUnit($unit),
        };
    }

    public function deductionWindowStart(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTimezone(new \DateTimeZone(self::TIMEZONE))->setTime(self::WINDOW_START_HOUR, 0);
    }

    /**
     * The window is left-closed, right-open: 07:00:00 belongs to the day, 22:00:00 does not -
     * that second is already past the boundary both providers enforce.
     */
    public function clampIntoWindow(\DateTimeImmutable $at): \DateTimeImmutable
    {
        $local = $at->setTimezone(new \DateTimeZone(self::TIMEZONE));

        if ($this->isWithinWindow($local)) {
            return $at;
        }

        if ($local->format('G') < self::WINDOW_START_HOUR) {
            return $local->setTime(self::WINDOW_START_HOUR, 0);
        }

        return $local->modify('+1 day')->setTime(self::WINDOW_START_HOUR, 0);
    }

    public function isWithinWindow(\DateTimeImmutable $at): bool
    {
        $local = $at->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $hour = (int) $local->format('G');

        return $hour >= self::WINDOW_START_HOUR && $hour < self::WINDOW_END_HOUR;
    }

    /**
     * The earliest moment a new-style alipay charge may be submitted for a deduction that should
     * happen "now": 48 hours of lead time, then clamped into the deductible window.
     */
    public function earliestExecutableAt(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $this->clampIntoWindow(
            $now->setTimezone(new \DateTimeZone(self::TIMEZONE))
                ->modify(\sprintf('+%d hours', self::ALIPAY_LEAD_TIME_HOURS))
        );
    }

    public function assertExpressibleBy(string $provider, string $unit, int $count, int $anchorDayOfMonth): void
    {
        if (\strtolower($provider) !== 'alipay') {
            return;
        }

        if (\strtolower($unit) === self::UNIT_MONTH && $anchorDayOfMonth > self::ALIPAY_MAX_ANCHOR_DAY_OF_MONTH) {
            throw PaymentException::paymentCycleNotExpressible($provider, $unit, $count, $anchorDayOfMonth);
        }
    }

    /**
     * Advances one period at a time so a clamped date feeds into the next step: Jan 31st + 2
     * months lands on Mar 28th (Jan 31 -> Feb 28 -> Mar 28), never on Mar 31st. Year handling
     * reuses the month arithmetic because "one year" is exactly the Feb-29th problem.
     */
    private function addPeriods(\DateTimeImmutable $cursor, int $count, int $monthsPerPeriod): \DateTimeImmutable
    {
        for ($i = 0; $i < $count; ++$i) {
            $cursor = $this->addOneMonthClamped($cursor, $monthsPerPeriod);
        }

        return $cursor;
    }

    private function addOneMonthClamped(\DateTimeImmutable $date, int $months): \DateTimeImmutable
    {
        $total = ((int) $date->format('Y')) * 12 + ((int) $date->format('n')) - 1 + $months;
        $year = intdiv($total, 12);
        $month = $total % 12 + 1;

        // The target month may not have the anchor day at all (31st in a 30-day month, Feb 29th
        // in a common year) - the last day of that month is the day the period actually ends.
        $lastDayOfMonth = (int) (new \DateTimeImmutable(\sprintf('%04d-%02d-01 00:00:00', $year, $month), $date->getTimezone()))
            ->modify('last day of this month')
            ->format('j');

        return $date->setDate($year, $month, min((int) $date->format('j'), $lastDayOfMonth));
    }
}
