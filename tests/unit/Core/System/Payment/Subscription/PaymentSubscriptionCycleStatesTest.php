<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Subscription\PaymentSubscriptionCycleStates;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentSubscriptionCycleStates::class)]
class PaymentSubscriptionCycleStatesTest extends TestCase
{
    #[DataProvider('names')]
    public function testTheNamesAreStable(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function names(): \Generator
    {
        yield 'state machine' => ['payment_subscription_cycle.state', PaymentSubscriptionCycleStates::STATE_MACHINE];
        yield 'state scheduled' => ['scheduled', PaymentSubscriptionCycleStates::STATE_SCHEDULED];
        yield 'state submitted' => ['submitted', PaymentSubscriptionCycleStates::STATE_SUBMITTED];
        yield 'state processing' => ['processing', PaymentSubscriptionCycleStates::STATE_PROCESSING];
        yield 'state succeeded' => ['succeeded', PaymentSubscriptionCycleStates::STATE_SUCCEEDED];
        yield 'state failed retryable' => ['failed_retryable', PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE];
        yield 'state failed final' => ['failed_final', PaymentSubscriptionCycleStates::STATE_FAILED_FINAL];
        yield 'state unknown' => ['unknown', PaymentSubscriptionCycleStates::STATE_UNKNOWN];
        yield 'state refunded' => ['refunded', PaymentSubscriptionCycleStates::STATE_REFUNDED];
        yield 'state partial refunded' => ['partial_refunded', PaymentSubscriptionCycleStates::STATE_PARTIAL_REFUNDED];
        yield 'state closed' => ['closed', PaymentSubscriptionCycleStates::STATE_CLOSED];
        yield 'action submit' => ['submit', PaymentSubscriptionCycleStates::ACTION_SUBMIT];
        yield 'action process' => ['process', PaymentSubscriptionCycleStates::ACTION_PROCESS];
        yield 'action succeed' => ['succeed', PaymentSubscriptionCycleStates::ACTION_SUCCEED];
        yield 'action fail retryable' => ['fail_retryable', PaymentSubscriptionCycleStates::ACTION_FAIL_RETRYABLE];
        yield 'action fail final' => ['fail_final', PaymentSubscriptionCycleStates::ACTION_FAIL_FINAL];
        yield 'action mark unknown' => ['mark_unknown', PaymentSubscriptionCycleStates::ACTION_MARK_UNKNOWN];
        yield 'action reschedule' => ['reschedule', PaymentSubscriptionCycleStates::ACTION_RESCHEDULE];
        yield 'action refund' => ['refund', PaymentSubscriptionCycleStates::ACTION_REFUND];
        yield 'action refund partially' => ['refund_partially', PaymentSubscriptionCycleStates::ACTION_REFUND_PARTIALLY];
        yield 'action close' => ['close', PaymentSubscriptionCycleStates::ACTION_CLOSE];
    }

    public function testEveryStateNameIsUsedOnlyOnce(): void
    {
        $states = [
            PaymentSubscriptionCycleStates::STATE_SCHEDULED,
            PaymentSubscriptionCycleStates::STATE_SUBMITTED,
            PaymentSubscriptionCycleStates::STATE_PROCESSING,
            PaymentSubscriptionCycleStates::STATE_SUCCEEDED,
            PaymentSubscriptionCycleStates::STATE_FAILED_RETRYABLE,
            PaymentSubscriptionCycleStates::STATE_FAILED_FINAL,
            PaymentSubscriptionCycleStates::STATE_UNKNOWN,
            PaymentSubscriptionCycleStates::STATE_REFUNDED,
            PaymentSubscriptionCycleStates::STATE_PARTIAL_REFUNDED,
            PaymentSubscriptionCycleStates::STATE_CLOSED,
        ];

        static::assertSame($states, array_values(array_unique($states)));
    }
}
