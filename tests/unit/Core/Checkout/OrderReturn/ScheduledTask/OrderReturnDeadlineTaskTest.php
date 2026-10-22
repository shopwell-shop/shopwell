<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\OrderReturn\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\OrderReturn\ScheduledTask\OrderReturnDeadlineTask;
use Shopwell\Core\Framework\Log\Package;

/**
 * The registered task name is stored in the database of every shop that ran the migration, so a
 * rename would leave the old row behind and the deadlines would silently stop being checked.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(OrderReturnDeadlineTask::class)]
class OrderReturnDeadlineTaskTest extends TestCase
{
    public function testTheTaskNameIsStable(): void
    {
        static::assertSame('order_return.deadline', OrderReturnDeadlineTask::getTaskName());
    }

    public function testTheDeadlinesAreCheckedEveryFifteenMinutes(): void
    {
        // a deadline is a day away, so the task does not need to run more often than that
        static::assertSame(900, OrderReturnDeadlineTask::getDefaultInterval());
    }

    public function testAFailedRunIsRetried(): void
    {
        static::assertTrue(OrderReturnDeadlineTask::shouldRescheduleOnFailure());
    }
}
