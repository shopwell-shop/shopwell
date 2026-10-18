<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription\Schedule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Subscription\Schedule\PaymentRetryPolicy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentRetryPolicy::class)]
class PaymentRetryPolicyTest extends TestCase
{
    private const SHANGHAI = 'Asia/Shanghai';

    private PaymentRetryPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PaymentRetryPolicy();
    }

    #[DataProvider('classifications')]
    public function testClassify(string $provider, ?string $failureCode, string $expected): void
    {
        static::assertSame($expected, $this->policy->classify($provider, $failureCode));
    }

    /**
     * `$attemptCount` counts the retries already consumed: 0 right after the first failure,
     * 5 after the fifth retry failed - and that call returns null, the ladder is exhausted.
     */
    #[DataProvider('backoffLadder')]
    public function testNextAttemptAtFollowsTheBackoffLadder(int $attemptCount, ?string $expected): void
    {
        $failedAt = $this->shanghai('2025-06-10 14:00:00');

        $result = $this->policy->nextAttemptAt('alipay', 'ACQ.BUYER_BALANCE_NOT_ENOUGH', $attemptCount, $failedAt);

        static::assertSame($expected, $result?->setTimezone(new \DateTimeZone(self::SHANGHAI))->format('Y-m-d H:i:s'));
    }

    public function testAlipayPaymentFailIsRetriedExactlyOnce(): void
    {
        $failedAt = $this->shanghai('2025-06-10 14:00:00');

        $firstRetry = $this->policy->nextAttemptAt('alipay', 'ACQ.PAYMENT_FAIL', 0, $failedAt);
        static::assertSame('2025-06-11 14:00:00', $firstRetry?->format('Y-m-d H:i:s'));

        // The generic "payment did not go through" hides permanent causes; alipay's docs advise
        // a single retry and nothing more.
        static::assertNull($this->policy->nextAttemptAt('alipay', 'ACQ.PAYMENT_FAIL', 1, $failedAt));
    }

    public function testFinalAndUnknownCodesNeverScheduleARetry(): void
    {
        $failedAt = $this->shanghai('2025-06-10 14:00:00');

        static::assertNull($this->policy->nextAttemptAt('alipay', 'ACQ.AGREEMENT_INVALID', 0, $failedAt));
        static::assertNull($this->policy->nextAttemptAt('alipay', 'ACQ.SYSTEM_ERROR', 0, $failedAt));
        static::assertNull($this->policy->nextAttemptAt('wechat', 'ORDER_ACCEPTED', 0, $failedAt));
        static::assertNull($this->policy->nextAttemptAt('alipay', 'ACQ.TRADE_HAS_SUCCESS', 0, $failedAt));
    }

    public function testWechatRuleLimitDoesNotRetryBeforeTheNextMorning(): void
    {
        // Backoff alone would land at 01:00; the day ban pushes it to the window start.
        $earlyMorning = $this->policy->nextAttemptAt(
            'wechat',
            'RULELIMIT',
            0,
            $this->shanghai('2025-06-10 01:00:00')
        );
        static::assertSame('2025-06-11 07:00:00', $earlyMorning?->format('Y-m-d H:i:s'));

        // Backoff alone would land at 14:00, which already satisfies the day ban.
        $afternoon = $this->policy->nextAttemptAt(
            'wechat',
            'RULELIMIT',
            0,
            $this->shanghai('2025-06-10 14:00:00')
        );
        static::assertSame('2025-06-11 14:00:00', $afternoon?->format('Y-m-d H:i:s'));
    }

    public function testBackoffOutsideTheWindowIsClampedIntoIt(): void
    {
        // A failure at 23:00 plus one day is 23:00 again - outside the window, so the retry is
        // pushed to the next morning instead of violating the providers' hours.
        $result = $this->policy->nextAttemptAt(
            'alipay',
            'ACQ.BUYER_BANKCARD_BALANCE_NOT_ENOUGH',
            0,
            $this->shanghai('2025-06-10 23:00:00')
        );

        static::assertSame('2025-06-12 07:00:00', $result?->format('Y-m-d H:i:s'));
    }

    #[DataProvider('untouchedByProvider')]
    public function testUnknownProvidersNeverRetry(?string $failureCode): void
    {
        static::assertSame(
            PaymentRetryPolicy::UNKNOWN,
            $this->policy->classify('stripe', $failureCode)
        );
        static::assertNull($this->policy->nextAttemptAt('stripe', $failureCode, 0, $this->shanghai('2025-06-10 14:00:00')));
    }

    public function testWechatFrequencyLimitedUsesTheStandardLadder(): void
    {
        $result = $this->policy->nextAttemptAt(
            'wechat',
            'FREQUENCY_LIMITED',
            0,
            $this->shanghai('2025-06-10 14:00:00')
        );

        static::assertSame('2025-06-11 14:00:00', $result?->format('Y-m-d H:i:s'));
    }

    public function testWechatOrderClosedIsNotAutoRetriedAndNotTerminal(): void
    {
        // A closed order needs a fresh out_trade_no - that is a caller decision, not a retry.
        static::assertSame(PaymentRetryPolicy::UNKNOWN, $this->policy->classify('wechat', 'ORDERCLOSED'));
        static::assertNull($this->policy->nextAttemptAt('wechat', 'ORDERCLOSED', 0, $this->shanghai('2025-06-10 14:00:00')));
    }

    /**
     * @return \Generator<string, array{string, ?string, string}>
     */
    public static function classifications(): \Generator
    {
        yield 'alipay insufficient balance is retryable' => [
            'alipay', 'ACQ.BUYER_BALANCE_NOT_ENOUGH', PaymentRetryPolicy::RETRYABLE,
        ];
        yield 'alipay empty bank card balance is retryable' => [
            'alipay', 'ACQ.BUYER_BANKCARD_BALANCE_NOT_ENOUGH', PaymentRetryPolicy::RETRYABLE,
        ];
        yield 'alipay no instruments is retryable' => [
            'alipay', 'ACQ.NO_PAYMENT_INSTRUMENTS_AVAILABLE', PaymentRetryPolicy::RETRYABLE,
        ];
        yield 'alipay generic payment fail is retryable (once)' => [
            'alipay', 'ACQ.PAYMENT_FAIL', PaymentRetryPolicy::RETRYABLE,
        ];
        yield 'alipay agreement invalid is final' => [
            'alipay', 'ACQ.AGREEMENT_INVALID', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay agreement missing is final' => [
            'alipay', 'ACQ.AGREEMENT_NOT_EXIST', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay merchant agreement invalid is final' => [
            'alipay', 'ACQ.MERCHANT_AGREEMENT_INVALID', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay single fee exceed is final' => [
            'alipay', 'ACQ.CYCLE_PAY_SINGLE_FEE_EXCEED', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay total fee exceed is final' => [
            'alipay', 'ACQ.CYCLE_PAY_TOTAL_FEE_EXCEED', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay total times exceed is final' => [
            'alipay', 'ACQ.CYCLE_PAY_TOTAL_TIMES_EXCEED', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay date mismatch is final' => [
            'alipay', 'ACQ.CYCLE_PAY_DATE_NOT_MATCH', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay agreement status not normal is final' => [
            'alipay', 'ACQ.AGREEMENT_STATUS_NOT_NORMAL', PaymentRetryPolicy::FINAL,
        ];
        yield 'alipay system error must be queried first' => [
            'alipay', 'ACQ.SYSTEM_ERROR', PaymentRetryPolicy::UNKNOWN,
        ];
        yield 'alipay trade already succeeded must be resolved by query, not failed' => [
            'alipay', 'ACQ.TRADE_HAS_SUCCESS', PaymentRetryPolicy::UNKNOWN,
        ];
        yield 'wechat not enough is retryable' => ['wechat', 'NOTENOUGH', PaymentRetryPolicy::RETRYABLE];
        yield 'wechat bank error is retryable' => ['wechat', 'BANKERROR', PaymentRetryPolicy::RETRYABLE];
        yield 'wechat frequency limited retries after a backoff' => [
            'wechat', 'FREQUENCY_LIMITED', PaymentRetryPolicy::RETRYABLE,
        ];
        yield 'wechat rule limit stops the day but not the cycle' => [
            'wechat', 'RULELIMIT', PaymentRetryPolicy::RETRYABLE,
        ];
        yield 'wechat contract missing is final' => ['wechat', 'CONTRACT_NOT_EXIST', PaymentRetryPolicy::FINAL];
        yield 'wechat contract error is final' => ['wechat', 'CONTRACTERROR', PaymentRetryPolicy::FINAL];
        yield 'wechat account error is final' => ['wechat', 'ACCOUNTERROR', PaymentRetryPolicy::FINAL];
        yield 'wechat abnormal user account is final' => ['wechat', 'USER_ACCOUNT_ABNORMAL', PaymentRetryPolicy::FINAL];
        yield 'wechat user missing is final' => ['wechat', 'USER_NOT_EXIST', PaymentRetryPolicy::FINAL];
        yield 'wechat user error is final' => ['wechat', 'USER_ERROR', PaymentRetryPolicy::FINAL];
        yield 'wechat system error must be queried first' => ['wechat', 'SYSTEMERROR', PaymentRetryPolicy::UNKNOWN];
        yield 'wechat accepted order is processing, not failed' => [
            'wechat', 'ORDER_ACCEPTED', PaymentRetryPolicy::UNKNOWN,
        ];
        yield 'wechat order already paid is success territory, query decides' => [
            'wechat', 'ORDERPAID', PaymentRetryPolicy::UNKNOWN,
        ];
        yield 'a code no provider ever sent must be queried first' => [
            'alipay', 'ACQ.SOMETHING_FUTURE', PaymentRetryPolicy::UNKNOWN,
        ];
        yield 'a missing code cannot be classified' => ['alipay', null, PaymentRetryPolicy::UNKNOWN];
    }

    /**
     * @return \Generator<string, array{int, ?string}>
     */
    public static function backoffLadder(): \Generator
    {
        yield 'first failure retries after 1 day' => [0, '2025-06-11 14:00:00'];
        yield 'second failure retries after 3 days' => [1, '2025-06-13 14:00:00'];
        yield 'third failure retries after 7 days' => [2, '2025-06-17 14:00:00'];
        yield 'fourth failure still retries after 7 days' => [3, '2025-06-17 14:00:00'];
        yield 'fifth failure schedules the last allowed retry' => [4, '2025-06-17 14:00:00'];
        yield 'after the sixth failed attempt the ladder is exhausted' => [5, null];
    }

    /**
     * @return \Generator<string, array{?string}>
     */
    public static function untouchedByProvider(): \Generator
    {
        yield 'a provider-specific code' => ['insufficient_funds'];
        yield 'no code at all' => [null];
    }

    private function shanghai(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone(self::SHANGHAI));
    }
}
