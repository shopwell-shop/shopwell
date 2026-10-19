<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\ScheduledTask\ReconcileOpenPaymentTransfersTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReconcileOpenPaymentTransfersTask::class)]
class ReconcileOpenPaymentTransfersTaskTest extends TestCase
{
    public function testItIsNamedAndRunsEveryHalfHour(): void
    {
        static::assertSame('payment.transfer.reconcile', ReconcileOpenPaymentTransfersTask::getTaskName());
        static::assertSame(1800, ReconcileOpenPaymentTransfersTask::getDefaultInterval());
    }

    /**
     * A failed batch is a channel that did not answer - the payouts in it are still open, so the next
     * run has to happen on its own.
     */
    public function testItIsRescheduledOnFailure(): void
    {
        static::assertTrue(ReconcileOpenPaymentTransfersTask::shouldRescheduleOnFailure());
    }

    public function testItRunsWithoutABag(): void
    {
        static::assertTrue(ReconcileOpenPaymentTransfersTask::shouldRun(new ParameterBag()));
    }

    public function testTheTaskIdIsMutable(): void
    {
        $task = new ReconcileOpenPaymentTransfersTask();

        static::assertNull($task->getTaskId());

        $task->setTaskId('task-id');

        static::assertSame('task-id', $task->getTaskId());
    }
}
