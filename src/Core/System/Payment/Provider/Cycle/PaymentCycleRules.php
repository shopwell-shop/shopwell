<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Cycle;

use Shopwell\Core\Framework\Log\Package;

/**
 * What a payment interface demands from a merchant-initiated (subscription) charge.
 *
 * The deduction calendar used to be global, with alipay's limits written into it as constants and
 * every other interface simply inheriting them - which happens to work for wechat because its limits
 * are looser, and would have silently applied alipay's rules to a third party interface as well. The
 * rules now travel with the interface:
 *
 *  - `maxAnchorDayOfMonth` - the highest day of the month a monthly plan may be anchored on, because
 *    alipay caps `period_rule_params.execute_time` at the 28th.
 *  - `leadTimeHours` - how long before the deduction a new-style charge has to be initiated.
 *  - `windowStartHour` / `windowEndHour` / `timezone` - the wall-clock window the interface accepts
 *    deduction calls in, left-closed and right-open.
 *  - `retryableFailureCodes` / `finalFailureCodes` / `unknownFailureCodes` - how the interface's own
 *    failure codes are read. A code that is on none of the lists is `unknown` on purpose: neither a
 *    retry nor declaring the mandate dead is safe without asking the interface.
 *  - `maxRetries` - how often a cycle may be attempted again before it is given up.
 *
 * The object is immutable and built by the interface in `AbstractPaymentProvider::getCycleRules()`. An
 * interface that returns `null` there cannot be charged repeatedly, which is the safe default: a
 * recurring charge the interface cannot express must be refused at plan level instead of silently
 * being turned into a different schedule.
 */
#[Package('framework')]
final readonly class PaymentCycleRules
{
    /**
     * The wall-clock timezone every calculation of the window happens in.
     */
    final public const DEFAULT_TIMEZONE = 'Asia/Shanghai';

    /**
     * @param int|null $maxAnchorDayOfMonth the highest day of the month a monthly plan may be anchored on, null means unrestricted
     * @param int $leadTimeHours how many hours before a deduction the charge has to be initiated
     * @param int $windowStartHour the hour the deduction window opens, inclusive
     * @param int $windowEndHour the hour the deduction window closes, exclusive
     * @param list<string> $retryableFailureCodes the failure codes that mean "money problem, the mandate is fine"
     * @param list<string> $finalFailureCodes the failure codes that mean "the mandate is dead"
     * @param list<string> $unknownFailureCodes the failure codes that mean "ask the interface what happened"
     * @param int $maxRetries how often one cycle may be charged again after a retryable failure
     * @param string $timezone the timezone the window is expressed in
     */
    public function __construct(
        public ?int $maxAnchorDayOfMonth = null,
        public int $leadTimeHours = 0,
        public int $windowStartHour = 0,
        public int $windowEndHour = 24,
        public array $retryableFailureCodes = [],
        public array $finalFailureCodes = [],
        public array $unknownFailureCodes = [],
        public int $maxRetries = 0,
        public string $timezone = self::DEFAULT_TIMEZONE,
    ) {
    }

    /**
     * Whether a monthly plan may be anchored on this day of the month.
     *
     * Refusing loudly beats silently clamping: the subscriber would be charged on a day they never
     * agreed to.
     */
    public function supportsAnchorDayOfMonth(int $anchorDayOfMonth): bool
    {
        return $this->maxAnchorDayOfMonth === null || $anchorDayOfMonth <= $this->maxAnchorDayOfMonth;
    }

    /**
     * Whether the very hour of this (local) time is inside the deduction window. The window is
     * left-closed and right-open: the start hour belongs to the day, the end hour does not.
     */
    public function isWithinWindow(int $hourOfDay): bool
    {
        return $hourOfDay >= $this->windowStartHour && $hourOfDay < $this->windowEndHour;
    }

    /**
     * How the interface's own failure code reads: `retryable`, `final` or `unknown`.
     *
     * A code the interface did not name is `unknown`, never `retryable`: retrying a code nobody
     * classified is what produces double charges, and declaring it dead is what produces a shipped but
     * unpaid order.
     *
     * @return 'retryable'|'final'|'unknown'
     */
    public function classifyFailureCode(?string $failureCode): string
    {
        if ($failureCode === null || $failureCode === '') {
            return 'unknown';
        }

        $code = strtoupper(trim($failureCode));

        if (\in_array($code, $this->retryableFailureCodes, true)) {
            return 'retryable';
        }

        if (\in_array($code, $this->finalFailureCodes, true)) {
            return 'final';
        }

        return 'unknown';
    }

    /**
     * Whether another attempt is allowed after this many failures already happened.
     */
    public function allowsAttemptAfter(int $consumedAttempts): bool
    {
        return $consumedAttempts < $this->maxRetries;
    }
}
