<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentOrdersTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentOrdersTask::class)]
class ReconcileOpenPaymentOrdersTaskTest extends TestCase
{
    public function testItIsNamedAndRunsEveryFiveMinutes(): void
    {
        static::assertSame('payment.order.reconcile', ReconcileOpenPaymentOrdersTask::getTaskName());
        static::assertSame(300, ReconcileOpenPaymentOrdersTask::getDefaultInterval());
    }

    public function testItIsRescheduledOnFailure(): void
    {
        static::assertTrue(ReconcileOpenPaymentOrdersTask::shouldRescheduleOnFailure());
    }

    public function testItRunsWithoutABag(): void
    {
        static::assertTrue(ReconcileOpenPaymentOrdersTask::shouldRun(new ParameterBag()));
    }

    public function testTheTaskIdIsMutable(): void
    {
        $task = new ReconcileOpenPaymentOrdersTask();

        static::assertNull($task->getTaskId());

        $task->setTaskId('task-id');

        static::assertSame('task-id', $task->getTaskId());
    }
}
