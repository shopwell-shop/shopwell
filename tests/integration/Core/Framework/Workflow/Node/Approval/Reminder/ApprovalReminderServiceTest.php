<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmission;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderDeliveryEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Reminder\ScheduledTask\ApprovalReminderTaskHandler;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReminderServiceTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var list<string>
     */
    private array $definitionIds = [];

    /**
     * @var list<string>
     */
    private array $reviewerIds = [];

    private Context $context;

    private string $actorId;

    private WorkflowService $workflows;

    private ApprovalReminderService $reminders;

    private bool $failDelivery = true;

    protected function setUp(): void
    {
        $this->actorId = Uuid::randomHex();
        $container = static::getContainer();
        $localeId = $container->get('locale.repository')->searchIds((new Criteria())->setLimit(1), Context::createDefaultContext())->firstId();
        $container->get('user.repository')->create([[
            'id' => $this->actorId, 'username' => $this->actorId, 'name' => 'Reminder test',
            'email' => $this->actorId . '@example.invalid', 'password' => 'A-strong-test-password-123!',
            'localeId' => $localeId, 'active' => true,
        ]], Context::createDefaultContext());
        $source = new AdminApiSource($this->actorId);
        $source->setPermissions(['workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide']);
        $this->context = new Context($source);
        $this->workflows = $container->get(WorkflowService::class);
        $this->reminders = $container->get(ApprovalReminderService::class);
    }

    protected function tearDown(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        foreach ($this->definitionIds as $id) {
            $definitionId = Uuid::fromHexToBytes($id);
            $connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_task task ON task.id = history.referenced_id INNER JOIN workflow_instance instance ON instance.id = task.instance_id WHERE instance.definition_id = :id', ['id' => $definitionId]);
            $connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_instance instance ON instance.id = history.referenced_id WHERE instance.definition_id = :id', ['id' => $definitionId]);
            $connection->delete('workflow_instance', ['definition_id' => $definitionId]);
            $connection->delete('workflow_execution_definition', ['id' => $definitionId]);
        }
        foreach ([$this->actorId, ...$this->reviewerIds] as $id) {
            $connection->delete('user', ['id' => Uuid::fromHexToBytes($id)]);
        }
    }

    public function testEvenAnAdministratorCannotForgeReminderRecordsThroughDal(): void
    {
        $task = $this->start();
        $source = new AdminApiSource($this->actorId);
        $source->setIsAdmin(true);
        $this->expectExceptionObject(WorkflowExecutionException::forbidden('Approval reminders can only be changed through reminder actions.'));

        try {
            static::getContainer()->get('workflow_approval_reminder.repository')->update([
                ['id' => $task->id, 'reminderCount' => 99],
            ], new Context($source));
        } finally {
            static::assertSame(0, $this->receipt($task->id)->reminderCount);
        }
    }

    public function testSchedulerProcessesMoreThanOnePageOfDueReceipts(): void
    {
        $task = $this->start();
        $tasks = [];
        for ($index = 0; $index < 101; ++$index) {
            $tasks[] = [
                'id' => Uuid::randomHex(),
                'instanceId' => $task->instanceId,
                'nodeId' => $task->nodeId,
                'nodeName' => $task->nodeName,
                'countersignType' => $task->countersignType,
                'assigneeId' => $this->actorId,
                'assigneeType' => 'user',
                'round' => 1,
                'sequence' => 1,
                'stateId' => $task->stateId,
            ];
        }
        $context = Context::createDefaultContext();
        static::getContainer()->get('workflow_task.repository')->create($tasks, $context);
        $ids = [$task->id, ...array_column($tasks, 'id')];
        static::getContainer()->get('workflow_approval_reminder.repository')->update(array_map(
            static fn (string $id): array => ['id' => $id, 'dueAt' => new \DateTimeImmutable('-1 minute')],
            $ids,
        ), $context);

        static::getContainer()->get(ApprovalReminderTaskHandler::class)->run();

        $receipts = static::getContainer()->get('workflow_approval_reminder.repository')->search(new Criteria($ids), $context);
        static::assertCount(102, $receipts);
        foreach ($receipts as $receipt) {
            static::assertInstanceOf(ApprovalReminderEntity::class, $receipt);
            static::assertSame(1, $receipt->reminderCount);
            static::assertNotNull($receipt->deliveredAt);
        }
        $this->reminders->runDue();
        static::assertSame(1, $this->receipt($task->id)->reminderCount);
    }

    public function testCallerMustCommitBeforeRequestingAReminder(): void
    {
        $task = $this->start();
        $connection = static::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        try {
            $this->reminders->remind($task->instanceId, $this->context);
            static::fail('A reminder must not join an outer transaction.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame('FRAMEWORK__WORKFLOW_REMINDER_TRANSACTION_ACTIVE', $exception->getErrorCode());
        } finally {
            $connection->rollBack();
        }
        static::assertSame(0, $this->receipt($task->id)->reminderCount);
        $this->reminders->remind($task->instanceId, $this->context);
        static::assertSame(1, $this->receipt($task->id)->reminderCount);
    }

    public function testReceiptIsCreatedWithTaskAndOnlyInitiatorMayRemind(): void
    {
        $task = $this->start();
        $receipt = $this->receipt($task->id);
        static::assertNotNull($receipt->dueAt);
        $createdAt = $task->getCreatedAt();
        static::assertNotNull($createdAt);
        static::assertSame(3600, $receipt->dueAt->getTimestamp() - $createdAt->getTimestamp());
        $this->reminders->remind($task->instanceId, $this->context);
        $receipt = $this->receipt($task->id);
        static::assertSame(1, $receipt->reminderCount);
        static::assertSame(ApprovalReminderEntity::MANUAL, $receipt->source);
        static::assertNotNull($receipt->deliveredAt);
        $current = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($task->instanceId, $this->context)->first();
        static::assertNotNull($current);
        static::assertTrue($current->isTodo());
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(true);
        $this->expectException(WorkflowExecutionException::class);
        $this->reminders->remind($task->instanceId, new Context($source));
    }

    public function testCooldownPreventsDuplicateManualReceipts(): void
    {
        $task = $this->start();
        $this->reminders->remind($task->instanceId, $this->context);
        try {
            $this->reminders->remind($task->instanceId, $this->context);
            static::fail('A repeated reminder must be rejected.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame(409, $exception->getStatusCode());
        }
        static::assertSame(1, $this->receipt($task->id)->reminderCount);
        $logs = static::getContainer()->get(WorkflowTaskFinder::class)->logsOf($task->instanceId, $this->context);
        static::assertCount(1, $logs->filter(static fn ($log): bool => $log->action === 'remind'));
    }

    public function testOverdueReminderRunsOnceAndDoesNotCompleteTask(): void
    {
        $task = $this->start();
        static::getContainer()->get('workflow_approval_reminder.repository')->update([[
            'id' => $task->id, 'dueAt' => new \DateTimeImmutable('-1 minute'),
        ]], Context::createDefaultContext());
        $this->reminders->runDue();
        $first = $this->receipt($task->id);
        static::assertSame(1, $first->reminderCount);
        static::assertNotNull($first->overdueRemindedAt);
        static::assertSame(ApprovalReminderEntity::OVERDUE, $first->source);
        $this->reminders->runDue();
        static::assertSame(1, $this->receipt($task->id)->reminderCount);
        static::assertTrue($this->workflows->loadInstance($task->instanceId, $this->context)->isRunning());
    }

    public function testFailedDeliveryRetriesTheSameIdentityWithoutDuplicatingReceipt(): void
    {
        $task = $this->start();
        $deliveries = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalReminderDeliveryEvent::class, function (ApprovalReminderDeliveryEvent $event) use (&$deliveries): void {
            $deliveries[] = $event->deliveryId;
            if ($this->failDelivery) {
                throw new \RuntimeException('Channel unavailable.');
            }
        });
        $this->reminders->remind($task->instanceId, $this->context);
        static::assertNull($this->receipt($task->id)->deliveredAt);
        $this->failDelivery = false;
        $this->reminders->runDue();
        static::assertNotNull($this->receipt($task->id)->deliveredAt);
        static::assertCount(2, $deliveries);
        static::assertSame($deliveries[0], $deliveries[1]);
        static::assertSame(1, $this->receipt($task->id)->reminderCount);
    }

    public function testCompletedAndReturnedTasksDoNotReceiveDueReminders(): void
    {
        foreach (['approve', 'returnToInitiator'] as $operation) {
            $task = $this->start();
            $flows = static::getContainer()->get(ApprovalWorkflowService::class);
            if ($operation === 'approve') {
                $flows->approve($task->id, 'Review complete', $this->context);
            } else {
                $flows->returnToInitiator($task->id, 'Review complete', $this->context);
            }
            static::getContainer()->get('workflow_approval_reminder.repository')->update([[
                'id' => $task->id, 'dueAt' => new \DateTimeImmutable('-1 minute'),
            ]], Context::createDefaultContext());
            $this->reminders->runDue();
            static::assertSame(0, $this->receipt($task->id)->reminderCount);
        }
    }

    public function testTransferringAnAddedReviewerRetainsTheirDeadline(): void
    {
        $task = $this->start();
        $addedUser = $this->reviewer();
        $recipient = $this->reviewer();
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $flows->participate($task->id, 'add-sign', $addedUser, 'Additional review', $this->context);
        $finder = static::getContainer()->get(WorkflowTaskFinder::class);
        $added = $finder->forInstance($task->instanceId, $this->context)->filter(static fn ($item): bool => $item->assigneeId === $addedUser)->first();
        static::assertNotNull($added);
        $expired = new \DateTimeImmutable('-5 minutes');
        static::getContainer()->get('workflow_approval_reminder.repository')->update([
            ['id' => $added->id, 'dueAt' => $expired],
        ], Context::createDefaultContext());
        $source = new AdminApiSource($addedUser);
        $source->setIsAdmin(true);
        $flows->participate($added->id, 'transfer', $recipient, 'Expert review', new Context($source));
        $replacement = $finder->forInstance($task->instanceId, $this->context)->filter(static fn ($item): bool => $item->assigneeId === $recipient)->first();
        static::assertNotNull($replacement);
        static::assertEquals($this->receipt($added->id)->dueAt, $this->receipt($replacement->id)->dueAt);
    }

    public function testExistingSnapshotCannotOverwriteACompetingReminder(): void
    {
        $task = $this->start();
        $connection = static::getContainer()->get(Connection::class);
        $connection->beginTransaction();
        static::assertSame(0, $this->receipt($task->id)->reminderCount);
        $other = DriverManager::getConnection($connection->getParams());
        $deliveryId = Uuid::randomHex();
        try {
            $other->update('workflow_approval_reminder', [
                'reminder_count' => 1,
                'last_reminded_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                'delivery_id' => Uuid::fromHexToBytes($deliveryId),
                'delivered_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
                'source' => 'manual',
            ], ['id' => Uuid::fromHexToBytes($task->id)]);
            $this->expectExceptionObject(WorkflowExecutionException::reminderRequiresCommit());
            $this->reminders->remind($task->instanceId, $this->context);
        } finally {
            $connection->rollBack();
            $other->close();
            static::assertSame($deliveryId, $this->receipt($task->id)->deliveryId);
            static::assertSame(1, $this->receipt($task->id)->reminderCount);
        }
    }

    public function testTransferCannotSilentlyDiscardAMissingSourceDeadline(): void
    {
        $task = $this->start();
        $recipient = $this->reviewer();
        static::getContainer()->get('workflow_approval_reminder.repository')->delete([
            ['id' => $task->id],
        ], Context::createDefaultContext());
        $this->expectExceptionObject(WorkflowExecutionException::invalidGraph('A transferred approval task requires its source reminder receipt.'));

        static::getContainer()->get(ApprovalWorkflowService::class)->participate(
            $task->id,
            'transfer',
            $recipient,
            'Expert review',
            $this->context,
        );
    }

    public function testTransferRetainsDeadlineAndAddedReviewerStartsTheirOwnClock(): void
    {
        $task = $this->start();
        $expired = new \DateTimeImmutable('-5 minutes');
        static::getContainer()->get('workflow_approval_reminder.repository')->update([['id' => $task->id, 'dueAt' => $expired]], Context::createDefaultContext());
        $recipient = $this->reviewer();
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $flows->participate($task->id, 'transfer', $recipient, 'Expert review', $this->context);
        $tasks = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($task->instanceId, $this->context);
        $replacement = $tasks->nextTodo();
        static::assertNotNull($replacement);
        static::assertEquals($this->receipt($task->id)->dueAt, $this->receipt($replacement->id)->dueAt);
        $additional = $this->reviewer();
        $actor = new AdminApiSource($recipient);
        $actor->setIsAdmin(true);
        $flows->participate($replacement->id, 'add-sign', $additional, 'Additional review', new Context($actor));
        $added = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($task->instanceId, $this->context)->filter(static fn ($item): bool => $item->assigneeId === $additional)->first();
        static::assertNotNull($added);
        static::assertGreaterThan(time() + 3500, $this->receipt($added->id)->dueAt?->getTimestamp());
        $this->reminders->runDue();
        static::assertSame(0, $this->receipt($task->id)->reminderCount);
        static::assertSame(1, $this->receipt($replacement->id)->reminderCount);
        static::assertSame(0, $this->receipt($added->id)->reminderCount);
    }

    public function testSequentialApproverAndResubmittedRoundReceiveFreshDeadlines(): void
    {
        $recipient = $this->reviewer();
        $task = $this->start('orderSign', [$this->actorId, $recipient]);
        static::getContainer()->get('workflow_approval_reminder.repository')->update([['id' => $task->id, 'dueAt' => new \DateTimeImmutable('-1 minute')]], Context::createDefaultContext());
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $flows->approve($task->id, null, $this->context);
        $next = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($task->instanceId, $this->context)->nextTodo();
        static::assertNotNull($next);
        static::assertSame($recipient, $next->assigneeId);
        static::assertGreaterThan(time() + 3500, $this->receipt($next->id)->dueAt?->getTimestamp());
        $source = new AdminApiSource($recipient);
        $source->setIsAdmin(true);
        $flows->returnToInitiator($next->id, 'Revise request', new Context($source));
        static::getContainer()->get(ApprovalResubmission::class)->resubmit($task->instanceId, 1, [], '', $this->context);
        $fresh = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($task->instanceId, $this->context)->nextTodo();
        static::assertNotNull($fresh);
        static::assertSame(2, $fresh->round);
        static::assertGreaterThan(time() + 3500, $this->receipt($fresh->id)->dueAt?->getTimestamp());
        $this->reminders->runDue();
        static::assertSame(0, $this->receipt($task->id)->reminderCount);
    }

    private function reviewer(): string
    {
        $id = Uuid::randomHex();
        $localeId = static::getContainer()->get('locale.repository')->searchIds((new Criteria())->setLimit(1), Context::createDefaultContext())->firstId();
        static::getContainer()->get('user.repository')->create([[
            'id' => $id, 'username' => $id, 'name' => 'Additional reviewer',
            'email' => $id . '@example.invalid', 'password' => 'A-strong-test-password-123!',
            'localeId' => $localeId, 'active' => true, 'admin' => true,
            'organizationUnitMappings' => [['organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => true]],
            'positionMappings' => [['positionId' => OrganizationDefaults::POSITION, 'organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => true]],
        ]], Context::createDefaultContext());
        $this->reviewerIds[] = $id;

        return $id;
    }

    /**
     * @param list<string>|null $approvers
     */
    private function start(string $mode = 'single', ?array $approvers = null): WorkflowTaskEntity
    {
        $definitionId = $this->workflows->createDefinition([
            'code' => Uuid::randomHex(), 'name' => 'Reminder review',
            'graphData' => ['nodes' => [
                ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => []]],
                ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                    'name' => 'Review', 'approvalStage' => true, 'assigneeType' => 'user',
                    'assigneeValue' => $approvers ?? [$this->actorId], 'countersignType' => $mode,
                    'approvalDeadline' => ['minutes' => 60, 'remind' => true],
                ]],
                ['id' => 'end', 'type' => 'snaker:end'],
            ], 'edges' => [
                ['id' => 'start-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
                ['id' => 'review-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
            ]],
        ], Context::createDefaultContext());
        $this->definitionIds[] = $definitionId;
        $this->workflows->publishDefinition($definitionId, Context::createDefaultContext());
        $id = $this->workflows->start($definitionId, [], 'Reminder review', $this->context);

        $task = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($id, $this->context)->first();
        static::assertNotNull($task);

        return $task;
    }

    private function receipt(string $taskId): ApprovalReminderEntity
    {
        $receipt = static::getContainer()->get('workflow_approval_reminder.repository')->search(new Criteria([$taskId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(ApprovalReminderEntity::class, $receipt);

        return $receipt;
    }
}
