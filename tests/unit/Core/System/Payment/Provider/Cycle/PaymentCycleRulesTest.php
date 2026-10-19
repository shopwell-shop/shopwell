<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Cycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Provider\Cycle\PaymentCycleRules;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentCycleRules::class)]
class PaymentCycleRulesTest extends TestCase
{
    public function testAnUnrestrictedCalendarAcceptsEveryAnchorDay(): void
    {
        $rules = new PaymentCycleRules();

        static::assertTrue($rules->supportsAnchorDayOfMonth(1));
        static::assertTrue($rules->supportsAnchorDayOfMonth(31));
        static::assertSame('Asia/Shanghai', $rules->timezone);
        static::assertFalse($rules->allowsAttemptAfter(0), 'Without retries the first failure is final.');
    }

    public function testTheAnchorDayCapIsInclusive(): void
    {
        $rules = new PaymentCycleRules(maxAnchorDayOfMonth: 28);

        static::assertTrue($rules->supportsAnchorDayOfMonth(28));
        static::assertFalse($rules->supportsAnchorDayOfMonth(29));
        static::assertFalse($rules->supportsAnchorDayOfMonth(31));
    }

    public function testTheDeductionWindowIsLeftClosedAndRightOpen(): void
    {
        $rules = new PaymentCycleRules(windowStartHour: 7, windowEndHour: 22);

        static::assertFalse($rules->isWithinWindow(6));
        static::assertTrue($rules->isWithinWindow(7));
        static::assertTrue($rules->isWithinWindow(21));
        static::assertFalse($rules->isWithinWindow(22));
        static::assertFalse($rules->isWithinWindow(23));
    }

    public function testClassifiesTheFailureCodesItKnows(): void
    {
        $rules = new PaymentCycleRules(
            retryableFailureCodes: ['NOTENOUGH'],
            finalFailureCodes: ['CONTRACT_NOT_EXIST'],
            unknownFailureCodes: ['ORDERPAID'],
        );

        static::assertSame('retryable', $rules->classifyFailureCode('NOTENOUGH'));
        static::assertSame('retryable', $rules->classifyFailureCode('notenough'), 'The codes are compared upper case.');
        static::assertSame('retryable', $rules->classifyFailureCode(' NOTENOUGH '));
        static::assertSame('final', $rules->classifyFailureCode('CONTRACT_NOT_EXIST'));
        static::assertSame('unknown', $rules->classifyFailureCode('ORDERPAID'));
    }

    /**
     * A code nobody classified must not be retried blindly: only a query of the interface may decide
     * its fate, because retrying what may already have gone through is a double charge.
     */
    public function testAnUnknownCodeIsUnknownAndNeverRetryable(): void
    {
        $rules = new PaymentCycleRules(retryableFailureCodes: ['NOTENOUGH']);

        static::assertSame('unknown', $rules->classifyFailureCode('SOMETHING_NEW'));
        static::assertSame('unknown', $rules->classifyFailureCode(null));
        static::assertSame('unknown', $rules->classifyFailureCode(''));
    }

    public function testCountsTheRetries(): void
    {
        $rules = new PaymentCycleRules(maxRetries: 5);

        static::assertTrue($rules->allowsAttemptAfter(0));
        static::assertTrue($rules->allowsAttemptAfter(4));
        static::assertFalse($rules->allowsAttemptAfter(5));
    }
}
