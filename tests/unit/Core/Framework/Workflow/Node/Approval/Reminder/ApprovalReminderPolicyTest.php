<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Reminder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderPolicy;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderPolicyEvent;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalReminderPolicy::class)]
class ApprovalReminderPolicyTest extends TestCase
{
    private MockClock $clock;

    private EventDispatcher $dispatcher;

    private ApprovalReminderPolicy $policy;

    private ApprovalReminderEntity $receipt;

    private WorkflowTaskEntity $task;

    private WorkflowInstanceEntity $instance;

    private Context $context;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-17T12:00:00Z');
        $this->dispatcher = new EventDispatcher();
        $this->policy = new ApprovalReminderPolicy($this->clock, $this->dispatcher);
        $this->receipt = new ApprovalReminderEntity();
        $this->task = new WorkflowTaskEntity();
        $this->task->nodeId = 'review';
        $this->task->stateMachineState = new StateMachineStateEntity();
        $this->task->stateMachineState->setTechnicalName('todo');
        $this->instance = new WorkflowInstanceEntity();
        $this->instance->initiatorId = '0123456789abcdef0123456789abcdef';
        $this->instance->currentNodeId = 'review';
        $this->instance->stateMachineState = new StateMachineStateEntity();
        $this->instance->stateMachineState->setTechnicalName('running');
        $source = new AdminApiSource($this->instance->initiatorId);
        $source->setPermissions(['workflow_instance:start']);
        $this->context = new Context($source);
    }

    public function testCooldownEndsAtExactlyTenMinutes(): void
    {
        $this->receipt->lastRemindedAt = $this->clock->now();
        $this->clock->sleep(599);
        static::assertFalse($this->manual());
        $this->clock->sleep(1);
        static::assertTrue($this->manual());
    }

    public function testDueBoundaryAndDisabledAutomaticReminder(): void
    {
        $this->receipt->dueAt = $this->clock->now()->modify('+1 second');
        $this->receipt->remindOnOverdue = true;
        static::assertFalse($this->overdue());
        $this->clock->sleep(1);
        static::assertTrue($this->overdue());
        $this->receipt->remindOnOverdue = false;
        static::assertFalse($this->overdue());
        $this->receipt->remindOnOverdue = true;
        $this->receipt->overdueRemindedAt = $this->clock->now();
        static::assertFalse($this->overdue());
    }

    public function testStaleTasksAndPendingDeliveryCannotBeReminded(): void
    {
        $this->instance->round = 2;
        static::assertFalse($this->manual());
        $this->instance->round = 1;
        $this->receipt->deliveryId = '0123456789abcdef0123456789abcdef';
        static::assertFalse($this->manual());
        $this->receipt->deliveredAt = $this->clock->now();
        static::assertTrue($this->manual());
        static::assertNotNull($this->task->stateMachineState);
        $this->task->stateMachineState->setTechnicalName('canceled');
        static::assertFalse($this->manual());
    }

    public function testPluginCanOnlyNarrowAdmission(): void
    {
        $observed = [];
        $this->dispatcher->addListener(ApprovalReminderPolicyEvent::class, static function (ApprovalReminderPolicyEvent $event) use (&$observed): void {
            $observed[] = $event->source;
            $event->deny();
        });
        static::assertFalse($this->manual());
        static::assertSame(['manual'], $observed);
        static::assertFalse($this->policy->allows($this->task, $this->instance, $this->receipt, 'unknown', $this->context));
    }

    private function manual(): bool
    {
        return $this->policy->allows($this->task, $this->instance, $this->receipt, ApprovalReminderEntity::MANUAL, $this->context);
    }

    private function overdue(): bool
    {
        return $this->policy->allows($this->task, $this->instance, $this->receipt, ApprovalReminderEntity::OVERDUE, $this->context);
    }
}
