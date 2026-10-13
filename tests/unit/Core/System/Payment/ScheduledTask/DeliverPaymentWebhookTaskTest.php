<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\ScheduledTask\DeliverPaymentWebhookTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeliverPaymentWebhookTask::class)]
class DeliverPaymentWebhookTaskTest extends TestCase
{
    public function testItIsNamedAndScheduledMinutely(): void
    {
        static::assertSame('payment.webhook.deliver', DeliverPaymentWebhookTask::getTaskName());
        static::assertSame(60, DeliverPaymentWebhookTask::getDefaultInterval());
    }

    public function testItIsRescheduledOnFailure(): void
    {
        static::assertTrue(DeliverPaymentWebhookTask::shouldRescheduleOnFailure());
    }

    public function testItRunsWithoutABag(): void
    {
        static::assertTrue(DeliverPaymentWebhookTask::shouldRun(new ParameterBag()));
    }

    public function testTheTaskIdIsMutable(): void
    {
        $task = new DeliverPaymentWebhookTask();

        static::assertNull($task->getTaskId());

        $task->setTaskId('task-id');

        static::assertSame('task-id', $task->getTaskId());
    }
}
