<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\ScheduledTask\DispatchPaymentEventsTask;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DispatchPaymentEventsTask::class)]
class DispatchPaymentEventsTaskTest extends TestCase
{
    public function testEventsAreRetriedEveryMinuteAndTaskFailuresAreRescheduled(): void
    {
        static::assertSame('payment.events.dispatch', DispatchPaymentEventsTask::getTaskName());
        static::assertSame(60, DispatchPaymentEventsTask::getDefaultInterval());
        static::assertTrue(DispatchPaymentEventsTask::shouldRescheduleOnFailure());
    }
}
