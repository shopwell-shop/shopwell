<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription\Schedule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;

/**
 * The period arithmetic and the deduction window of merchant-initiated subscriptions.
 *
 * Nothing about a specific interface is written down here: the window, the lead time and the anchor
 * day cap arrive as {@see PaymentCycleRules} from the interface that is being charged. A calendar
 * that carried alipay's limits as constants would apply them to every interface added later - it
 * happened to work for wechat because its limits are looser, and would have silently mis-scheduled
 * the third one.
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
     * The period boundary is a calendar fact of the platform, not a limit of an interface, so it is
     * computed in the platform timezone even when the interface schedules in another one.
     */
    private const PERIOD_TIMEZONE = PaymentCycleRules::DEFAULT_TIMEZONE;

    public function nextPeriodEnd(\DateTimeImmutable $anchor, string $unit, int $count): \DateTimeImmutable
    {
        if ($count < 1) {
            throw PaymentException::invalidPeriodCount($count);
        }

        $cursor = $anchor->setTimezone(new \DateTimeZone(self::PERIOD_TIMEZONE))->setTime(0, 0);

        return match ($unit) {
            self::UNIT_DAY => $cursor->modify(\sprintf('+%d days', $count)),
            self::UNIT_WEEK => $cursor->modify(\sprintf('+%d weeks', $count)),
            self::UNIT_MONTH => $this->addPeriods($cursor, $count, 1),
            self::UNIT_YEAR => $this->addPeriods($cursor, $count, 12),
            default => throw PaymentException::invalidPeriodUnit($unit),
        };
    }

    public function deductionWindowStart(\DateTimeImmutable $day, PaymentCycleRules $rules): \DateTimeImmutable
    {
        return $day->setTimezone(new \DateTimeZone($rules->timezone))->setTime($rules->windowStartHour, 0);
    }

    /**
     * The window is left-closed, right-open: 07:00:00 belongs to the day, 22:00:00 does not -
     * that second is already past the boundary the interface enforces.
     */
    public function clampIntoWindow(\DateTimeImmutable $at, PaymentCycleRules $rules): \DateTimeImmutable
    {
        $local = $at->setTimezone(new \DateTimeZone($rules->timezone));

        if ($this->isWithinWindow($local, $rules)) {
            return $at;
        }

        if ((int) $local->format('G') < $rules->windowStartHour) {
            return $local->setTime($rules->windowStartHour, 0);
        }

        return $local->modify('+1 day')->setTime($rules->windowStartHour, 0);
    }

    public function isWithinWindow(\DateTimeImmutable $at, PaymentCycleRules $rules): bool
    {
        return $rules->isWithinWindow((int) $at->setTimezone(new \DateTimeZone($rules->timezone))->format('G'));
    }

    /**
     * The earliest moment a new-style charge may be submitted for a deduction that should happen
     * "now": the lead time of the interface, then clamped into its deductible window.
     */
    public function earliestExecutableAt(\DateTimeImmutable $now, PaymentCycleRules $rules): \DateTimeImmutable
    {
        return $this->clampIntoWindow(
            $now->setTimezone(new \DateTimeZone($rules->timezone))
                ->modify(\sprintf('+%d hours', $rules->leadTimeHours)),
            $rules
        );
    }

    /**
     * Refuses a monthly plan the interface cannot express, instead of clamping it: the subscriber
     * would be charged on a day they never agreed to.
     */
    public function assertExpressibleBy(string $provider, PaymentCycleRules $rules, string $unit, int $count, int $anchorDayOfMonth): void
    {
        if (\strtolower($unit) !== self::UNIT_MONTH || $rules->supportsAnchorDayOfMonth($anchorDayOfMonth)) {
            return;
        }

        throw PaymentException::paymentCycleNotExpressible($provider, $unit, $count, $anchorDayOfMonth);
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
