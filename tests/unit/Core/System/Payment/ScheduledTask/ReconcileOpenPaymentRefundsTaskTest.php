<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentRefundsTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentRefundsTask::class)]
class ReconcileOpenPaymentRefundsTaskTest extends TestCase
{
    public function testItIsNamedAndRunsEveryFiveMinutes(): void
    {
        static::assertSame('payment.refund.reconcile', ReconcileOpenPaymentRefundsTask::getTaskName());
        static::assertSame(300, ReconcileOpenPaymentRefundsTask::getDefaultInterval());
    }

    public function testItIsRescheduledOnFailure(): void
    {
        static::assertTrue(ReconcileOpenPaymentRefundsTask::shouldRescheduleOnFailure());
    }

    public function testItRunsWithoutABag(): void
    {
        static::assertTrue(ReconcileOpenPaymentRefundsTask::shouldRun(new ParameterBag()));
    }

    public function testTheTaskIdIsMutable(): void
    {
        $task = new ReconcileOpenPaymentRefundsTask();

        static::assertNull($task->getTaskId());

        $task->setTaskId('task-id');

        static::assertSame('task-id', $task->getTaskId());
    }
}
