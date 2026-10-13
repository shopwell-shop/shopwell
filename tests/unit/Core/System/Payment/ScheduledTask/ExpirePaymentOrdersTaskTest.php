<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\ScheduledTask\ExpirePaymentOrdersTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExpirePaymentOrdersTask::class)]
class ExpirePaymentOrdersTaskTest extends TestCase
{
    public function testItIsNamedAndScheduledMinutely(): void
    {
        static::assertSame('payment.order.expire', ExpirePaymentOrdersTask::getTaskName());
        static::assertSame(60, ExpirePaymentOrdersTask::getDefaultInterval());
    }

    public function testItIsRescheduledOnFailure(): void
    {
        static::assertTrue(ExpirePaymentOrdersTask::shouldRescheduleOnFailure());
    }

    public function testItRunsWithoutABag(): void
    {
        static::assertTrue(ExpirePaymentOrdersTask::shouldRun(new ParameterBag()));
    }

    public function testTheTaskIdIsMutable(): void
    {
        $task = new ExpirePaymentOrdersTask();

        static::assertNull($task->getTaskId());

        $task->setTaskId('task-id');

        static::assertSame('task-id', $task->getTaskId());
    }
}
