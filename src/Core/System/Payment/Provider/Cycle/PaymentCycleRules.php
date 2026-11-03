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
 *  - `retryableFailureCodes` / `finalFailureCodes` - how the interface's own failure codes are read.
 *    A code that is on neither list is `unknown` on purpose: neither a retry nor declaring the mandate
 *    dead is safe without asking the interface.
 *  - `maxRetries` - how often a cycle may be attempted again before it is given up.
 *  - `singleRetryFailureCodes` - the codes the interface documents as "retry once", where the second
 *    attempt is the last one no matter how many retries are left. A generic "the payment did not go
 *    through" hides permanent causes behind a temporary facade, and retrying it burns the window.
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
     * @param int $maxRetries how often one cycle may be charged again after a retryable failure
     * @param list<string> $singleRetryFailureCodes the retryable codes that may be retried exactly once
     * @param string $timezone the timezone the window is expressed in
     */
    public function __construct(
        public ?int $maxAnchorDayOfMonth = null,
        public int $leadTimeHours = 0,
        public int $windowStartHour = 0,
        public int $windowEndHour = 24,
        public array $retryableFailureCodes = [],
        public array $finalFailureCodes = [],
        public int $maxRetries = 0,
        public array $singleRetryFailureCodes = [],
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

    /**
     * Whether the interface accepts one more attempt for this code, given how many were consumed.
     *
     * Two limits apply and the stricter one wins: the per cycle `maxRetries`, and the one retry a
     * `singleRetryFailureCodes` code gets regardless of how much of the ladder is left.
     */
    public function allowsAnotherAttempt(?string $failureCode, int $consumedAttempts): bool
    {
        if (!$this->allowsAttemptAfter($consumedAttempts)) {
            return false;
        }

        return !$this->isPastSingleRetry($failureCode, $consumedAttempts);
    }

    /**
     * Whether the one retry of a `singleRetryFailureCodes` code is already spent.
     */
    public function isPastSingleRetry(?string $failureCode, int $consumedAttempts): bool
    {
        if ($failureCode === null || $failureCode === '') {
            return false;
        }

        if (!\in_array(strtoupper(trim($failureCode)), $this->singleRetryFailureCodes, true)) {
            return false;
        }

        // the first failure consumes the first retry, so one consumed attempt means the retry that
        // was allowed has already been spent
        return $consumedAttempts >= 1;
    }
}
