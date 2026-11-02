<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription\Schedule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;

/**
 * Classifies channel failure codes for subscription deductions and derives the next retry time.
 *
 * The policy owns no knowledge of a single interface: which code means "money problem", which one
 * means "the mandate is dead" and how much of the retry ladder is left all arrive as
 * {@see PaymentCycleRules} from the interface that is being charged. A policy that kept its own copy
 * of those lists would drift away from the declaration the first time an interface reclassified a
 * code, and nothing would fail - the cycles would simply be retried against a rule the channel no
 * longer follows.
 *
 * Three classes, and the differences are not cosmetic:
 *
 * - `RETRYABLE`: the channel said "money problem", the mandate is fine - back off and try again.
 * - `FINAL`: the mandate itself is dead (unsigned, over limit, blacklisted) - retrying would only
 *   burn API calls; the cycle goes to `failed_final`.
 * - `UNKNOWN`: the channel did not tell us what happened (system errors, async acceptance,
 *   "already paid"). Neither retrying nor declaring failure is safe here - only a query of the
 *   channel may decide. A double charge or a shipped-but-unpaid cycle both originate from
 *   guessing at this class, so anything not on a known list lands here on purpose.
 *
 * `$consumedAttempts` counts the retries already used up for the cycle: 0 right after the first
 * failure, 1 after the first retry failed, and so on. The backoff ladder below therefore reads
 * "after the 1st failure wait 1 day, after the 2nd wait 3 days, ...".
 */
#[Package('framework')]
final class PaymentRetryPolicy
{
    public const RETRYABLE = 'retryable';
    public const FINAL = 'final';
    public const UNKNOWN = 'unknown';

    public function __construct(private readonly PaymentCycleCalendar $calendar = new PaymentCycleCalendar())
    {
    }

    /**
     * An interface that cannot be charged repeatedly has no rules, and every code of it is unknown:
     * a cycle of an interface that declares no `MANDATE` must never be retried on a guess.
     *
     * @return self::RETRYABLE|self::FINAL|self::UNKNOWN
     */
    public function classify(?PaymentCycleRules $rules, ?string $failureCode): string
    {
        return $rules?->classifyFailureCode($failureCode) ?? self::UNKNOWN;
    }

    /**
     * Returns the earliest moment the cycle may be charged again, already clamped into the
     * deduction window of the interface; null means "stop retrying this cycle".
     */
    public function nextAttemptAt(
        ?PaymentCycleRules $rules,
        ?string $failureCode,
        int $consumedAttempts,
        \DateTimeImmutable $failedAt
    ): ?\DateTimeImmutable {
        if ($rules === null || $rules->classifyFailureCode($failureCode) !== self::RETRYABLE) {
            return null;
        }

        if (!$rules->allowsAnotherAttempt($failureCode, $consumedAttempts)) {
            return null;
        }

        $days = match (true) {
            $consumedAttempts === 0 => 1,
            $consumedAttempts === 1 => 3,
            default => 7,
        };

        $candidate = $failedAt
            ->setTimezone(new \DateTimeZone($rules->timezone))
            ->modify(\sprintf('+%d days', $days));

        // clamping covers the per day limits as well - a code that bans the rest of the day lands on
        // the next opening of the window without the policy knowing which code that is
        return $this->calendar->clampIntoWindow($candidate, $rules);
    }
}
