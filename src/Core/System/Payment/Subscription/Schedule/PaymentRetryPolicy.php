<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Subscription\Schedule;

use Shopwell\Core\Framework\Log\Package;

/**
 * Classifies channel failure codes for subscription deductions and derives the next retry time.
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
 * `$attemptCount` counts the retries already consumed for the cycle: 0 right after the first
 * failure, 1 after the first retry failed, and so on. The backoff ladder below therefore reads
 * "after the 1st failure wait 1 day, after the 2nd wait 3 days, ...".
 */
#[Package('framework')]
final class PaymentRetryPolicy
{
    public const RETRYABLE = 'retryable';
    public const FINAL = 'final';
    public const UNKNOWN = 'unknown';

    /**
     * At most five retries per cycle; the call after the sixth failed attempt returns null.
     */
    public const MAX_RETRIES = 5;

    private const WINDOW_START_HOUR = 7;

    private const PROVIDER_ALIPAY = 'alipay';
    private const PROVIDER_WECHAT = 'wechat';

    private const ALIPAY_RETRYABLE = [
        'ACQ.BUYER_BALANCE_NOT_ENOUGH',
        'ACQ.BUYER_BANKCARD_BALANCE_NOT_ENOUGH',
        'ACQ.NO_PAYMENT_INSTRUMENTS_AVAILABLE',
        'ACQ.PAYMENT_FAIL',
    ];

    private const ALIPAY_FINAL = [
        'ACQ.CYCLE_PAY_SINGLE_FEE_EXCEED',
        'ACQ.CYCLE_PAY_TOTAL_FEE_EXCEED',
        'ACQ.CYCLE_PAY_TOTAL_TIMES_EXCEED',
        'ACQ.CYCLE_PAY_DATE_NOT_MATCH',
        'ACQ.AGREEMENT_NOT_EXIST',
        'ACQ.AGREEMENT_INVALID',
        'ACQ.AGREEMENT_STATUS_NOT_NORMAL',
        'ACQ.MERCHANT_AGREEMENT_INVALID',
    ];

    private const ALIPAY_UNKNOWN = [
        // Alipay's new charge flow accepts asynchronously: SYSTEM_ERROR may mean the charge
        // actually went through. TRADE_HAS_SUCCESS is not a failure at all - the query path
        // resolves it to success, and classifying it as a failure would refund nothing.
        'ACQ.SYSTEM_ERROR',
        'ACQ.TRADE_HAS_SUCCESS',
    ];

    private const WECHAT_RETRYABLE = [
        'NOTENOUGH',
        'BANKERROR',
        // The docs only say "back off and retry"; the standard ladder is the backoff.
        'FREQUENCY_LIMITED',
        // Stops the *day*, not the cycle - tomorrow's window is legal again.
        'RULELIMIT',
    ];

    private const WECHAT_FINAL = [
        'CONTRACT_NOT_EXIST',
        'CONTRACTERROR',
        'ACCOUNTERROR',
        'USER_ACCOUNT_ABNORMAL',
        'USER_NOT_EXIST',
        'USER_ERROR',
    ];

    private const WECHAT_UNKNOWN = [
        // PROCESSING: wechat forbids a second charge while the first is in flight.
        'SYSTEMERROR',
        'ORDER_ACCEPTED',
        // Already paid - the query path resolves this to success, never to a retry.
        'ORDERPAID',
        // Needs a fresh out_trade_no rather than a retry of the same one; still not terminal for
        // the cycle, so it must not be classified FINAL. Rescheduling with a new number is the
        // caller's decision, not the policy's.
        'ORDERCLOSED',
    ];

    public function __construct(private readonly PaymentCycleCalendar $calendar = new PaymentCycleCalendar())
    {
    }

    /**
     * @return self::RETRYABLE|self::FINAL|self::UNKNOWN
     */
    public function classify(string $provider, ?string $failureCode): string
    {
        if ($failureCode === null || $failureCode === '') {
            // A failure without a code is unproven - only a query may decide its fate.
            return self::UNKNOWN;
        }

        $code = \strtoupper(\trim($failureCode));

        return match (\strtolower($provider)) {
            self::PROVIDER_ALIPAY => $this->inList($code, self::ALIPAY_RETRYABLE, self::ALIPAY_FINAL, self::ALIPAY_UNKNOWN),
            self::PROVIDER_WECHAT => $this->inList($code, self::WECHAT_RETRYABLE, self::WECHAT_FINAL, self::WECHAT_UNKNOWN),
            default => self::UNKNOWN,
        };
    }

    /**
     * Returns the earliest moment the cycle may be charged again, already clamped into the
     * 07:00-22:00 Beijing window; null means "stop retrying this cycle".
     */
    public function nextAttemptAt(string $provider, ?string $failureCode, int $attemptCount, \DateTimeImmutable $failedAt): ?\DateTimeImmutable
    {
        if ($this->classify($provider, $failureCode) !== self::RETRYABLE) {
            return null;
        }

        if ($attemptCount >= self::MAX_RETRIES) {
            return null;
        }

        // Alipay's docs suggest retrying PAYMENT_FAIL exactly once: it is a generic "payment did
        // not go through" that hides a lot of permanent causes behind a temporary facade.
        if (\strtolower($provider) === self::PROVIDER_ALIPAY
            && \strtoupper((string) $failureCode) === 'ACQ.PAYMENT_FAIL'
            && $attemptCount >= 1) {
            return null;
        }

        $days = match (true) {
            $attemptCount === 0 => 1,
            $attemptCount === 1 => 3,
            default => 7,
        };

        $candidate = $failedAt->setTimezone(new \DateTimeZone('Asia/Shanghai'))->modify(\sprintf('+%d days', $days));

        if (\strtolower($provider) === self::PROVIDER_WECHAT
            && \strtoupper((string) $failureCode) === 'RULELIMIT') {
            // RULELIMIT bans the rest of the day; the ladder is already at least one day out, so
            // flooring the candidate to 07:00 of its own day can never land on the failed day.
            $candidate = $this->laterOf($candidate, $candidate->setTime(self::WINDOW_START_HOUR, 0));
        }

        return $this->calendar->clampIntoWindow($candidate);
    }

    /**
     * @param list<string> $retryable
     * @param list<string> $final
     * @param list<string> $unknown
     *
     * @return self::RETRYABLE|self::FINAL|self::UNKNOWN
     */
    private function inList(string $code, array $retryable, array $final, array $unknown): string
    {
        if (\in_array($code, $retryable, true)) {
            return self::RETRYABLE;
        }

        if (\in_array($code, $final, true)) {
            return self::FINAL;
        }

        if (\in_array($code, $unknown, true)) {
            return self::UNKNOWN;
        }

        // Codes the providers invent later must not be retried blindly: query first.
        return self::UNKNOWN;
    }

    private function laterOf(\DateTimeImmutable $a, \DateTimeImmutable $b): \DateTimeImmutable
    {
        return $a->getTimestamp() >= $b->getTimestamp() ? $a : $b;
    }
}
