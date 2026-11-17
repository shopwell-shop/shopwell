<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyEntity;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceEndedEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalOperationRegistrationEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmission;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalResubmittingEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskActionsEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Extension\ApprovalTaskOperationExtension;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalResubmissionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var list<TestBrowser>
     */
    private array $actors;

    /**
     * @var list<string>
     */
    private array $userIds;

    private Context $context;

    protected function setUp(): void
    {
        $this->actors = [];
        $this->userIds = [];
        for ($index = 0; $index < 4; ++$index) {
            $this->actors[] = $this->createClient(permissions: ['workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide']);
            $this->userIds[] = Uuid::fromBytesToHex(static::getContainer()->get(Connection::class)->fetchOne(
                'SELECT id FROM user WHERE username = :username',
                ['username' => $this->apiUsernames[array_key_last($this->apiUsernames) ?? 0]],
            ));
            $userId = $this->userIds[array_key_last($this->userIds)];
            static::getContainer()->get('user.repository')->update([[
                'id' => $userId,
                'email' => $userId . '@example.com',
                'organizationUnitMappings' => [['organizationUnitId' => OrganizationDefaults::UNIT, 'isPrimary' => true]],
                'positionMappings' => [[
                    'positionId' => OrganizationDefaults::POSITION,
                    'organizationUnitId' => OrganizationDefaults::UNIT,
                    'isPrimary' => true,
                ]],
            ]], Context::createDefaultContext());
        }
        array_multisort($this->userIds, $this->actors);
        $source = new AdminApiSource($this->userIds[0]);
        $source->setIsAdmin(true);
        $this->context = new Context($source);
    }

    public function testReturnCancelsPendingWorkWithoutEndingTheInstance(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        $this->participate($task, 0, 'add-sign', 2);
        $ended = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(WorkflowInstanceEndedEvent::EVENT_NAME, static function ($event) use (&$ended): void {
            $ended[] = $event;
        });
        $this->returnTask($task);
        static::assertSame([], $ended);

        $instance = $this->instance($instanceId);
        static::assertSame('returned', $instance->state());
        static::assertNull($instance->endedAt);
        static::assertNull($instance->result);
        static::assertSame(0, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/approve', []);
        static::assertSame(409, $this->actors[0]->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function returnModes(): iterable
    {
        yield 'sequential' => [WorkflowCountersignTypes::ORDER_SIGN];
        yield 'first decision' => [WorkflowCountersignTypes::FIRST_DECISION];
    }

    #[DataProvider('returnModes')]
    public function testAddedReviewerCanReturnAndResubmitRestoresOriginalParticipants(string $mode): void
    {
        $instanceId = $this->start($mode);
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'add-sign', 2);
        $added = $this->todo($instanceId, 2);
        $this->actors[2]->jsonRequest('POST', '/api/_action/workflow/task/' . $added->id . '/return', ['comment' => 'Revise the evidence']);
        static::assertSame(204, $this->actors[2]->getResponse()->getStatusCode());
        static::assertSame('returned', $this->instance($instanceId)->state());
        static::assertSame(0, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));

        $this->resubmit($instanceId, 0, 1, ['reason' => 'Revised evidence']);
        $current = $this->tasks()->forInstance($instanceId, $this->context)->filter(static fn ($task): bool => $task->isTodo());
        static::assertCount($mode === WorkflowCountersignTypes::ORDER_SIGN ? 1 : 2, $current);
        foreach ($current as $task) {
            static::assertSame(2, $task->round);
            static::assertContains($task->assigneeId, [$this->userIds[0], $this->userIds[1]]);
        }
        $this->actors[2]->jsonRequest('POST', '/api/_action/workflow/task/' . $added->id . '/approve', []);
        static::assertSame(409, $this->actors[2]->getResponse()->getStatusCode());
    }

    public function testResubmitKeepsSnapshotAndStartsANewRound(): void
    {
        $instanceId = $this->start('countersign');
        $before = $this->instance($instanceId);
        $oldTask = $this->todo($instanceId, 0);
        $this->returnTask($oldTask);
        $this->resubmit($instanceId, 0, 1, ['reason' => 'Revised request']);

        $after = $this->instance($instanceId);
        static::assertTrue($after->isRunning());
        static::assertSame(2, $after->round);
        static::assertSame($before->snapshot(), $after->snapshot());
        static::assertSame($before->definitionVersion, $after->definitionVersion);
        static::assertSame([...($before->ext ?? []), 'approvalFormVersion' => 1], $after->ext);
        static::assertSame(['reason' => 'Revised request'], $after->form());
        static::assertSame(2, $this->todo($instanceId, 0)->round);
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $oldTask->id . '/approve', []);
        static::assertSame(409, $this->actors[0]->getResponse()->getStatusCode());
        $this->actors[0]->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        $detail = json_decode((string) $this->actors[0]->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
        static::assertCount(2, $detail['approvalStages'][0]['tasks']);
        static::assertSame(1, $detail['history'][0]['round']);
        static::assertSame('resubmit', $detail['logs'][2]['action']);
        static::assertSame(1, $detail['logs'][2]['payload']['previousRound']);
        static::assertArrayNotHasKey('previousFormData', $detail['logs'][2]['payload']);
        static::assertSame('Original request', $detail['formChanges'][0]['changes']['reason']['before']);
        static::assertSame('Revised request', $detail['formChanges'][0]['changes']['reason']['after']);
    }

    public function testOnlyInitiatorCanResubmitAndStaleRoundsAreRejected(): void
    {
        $instanceId = $this->start('countersign');
        $this->returnTask($this->todo($instanceId, 0));
        $this->resubmit($instanceId, 1, 1, ['reason' => 'Revised'], 403);
        $this->resubmit($instanceId, 0, 2, ['reason' => 'Revised'], 409);
        $this->resubmit($instanceId, 0, 1, ['reason' => ''], 400);
        static::assertSame(1, $this->instance($instanceId)->round);
        static::assertSame('returned', $this->instance($instanceId)->state());
    }

    public function testReturnRequiresCommentAndTaskOwnership(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        $this->actors[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => 'Revise']);
        static::assertSame(404, $this->actors[1]->getResponse()->getStatusCode());
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => ' ']);
        static::assertSame(400, $this->actors[0]->getResponse()->getStatusCode());
        static::assertTrue($this->instance($instanceId)->isRunning());
    }

    public function testReturnedInstanceMayBeCanceledByInitiator(): void
    {
        $instanceId = $this->start('countersign');
        $this->returnTask($this->todo($instanceId, 0));
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/instance/' . $instanceId . '/cancel', ['reason' => 'Withdraw']);
        static::assertSame(204, $this->actors[0]->getResponse()->getStatusCode());
        static::assertSame('canceled', $this->instance($instanceId)->state());
        $this->resubmit($instanceId, 0, 1, ['reason' => 'Revised'], 409);
    }

    public function testResubmissionValidationFailureRollsBackTheRoundAndTodos(): void
    {
        $instanceId = $this->start('countersign');
        $this->returnTask($this->todo($instanceId, 0));
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalResubmittingEvent::class, static function (): void {
            throw new \RuntimeException('Business validation failed');
        });
        $this->expectExceptionObject(new \RuntimeException('Business validation failed'));
        try {
            static::getContainer()->get(ApprovalResubmission::class)->resubmit($instanceId, 1, ['reason' => 'Revised'], 'Explanation', $this->context, formVersion: 0);
        } finally {
            static::assertSame(1, $this->instance($instanceId)->round);
            static::assertSame('returned', $this->instance($instanceId)->state());
            static::assertSame(0, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
            static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());
        }
    }

    public function testCopyReceiptsRemainDistinctAcrossRounds(): void
    {
        $instanceId = $this->start('countersign', withCopy: true);
        $copies = static::getContainer()->get('workflow_copy.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('instanceId', $instanceId));
        $first = $copies->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(WorkflowCopyEntity::class, $first);
        $copies->update([['id' => $first->id, 'readAt' => new \DateTimeImmutable()]], Context::createDefaultContext());
        $this->returnTask($this->todo($instanceId, 0));
        $this->resubmit($instanceId, 0, 1, ['reason' => 'Revised']);
        $receipts = $copies->search($criteria, $this->context)->getEntities();
        static::assertInstanceOf(WorkflowCopyCollection::class, $receipts);
        static::assertCount(2, $receipts);
        $original = $receipts->get($first->id);
        static::assertNotNull($original);
        static::assertNotNull($original->readAt);
        $new = $receipts->filter(static fn ($copy): bool => $copy->round === 2)->first();
        static::assertNotNull($new);
        static::assertNull($new->readAt);
    }

    public function testOldExecutionCannotResumeAfterResubmission(): void
    {
        $instanceId = $this->start('countersign');
        $executionId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM workflow_node_execution WHERE instance_id = :instance AND status = \'waiting\'',
            ['instance' => Uuid::fromHexToBytes($instanceId)],
        );
        static::assertIsString($executionId);
        $this->returnTask($this->todo($instanceId, 0));
        $this->resubmit($instanceId, 0, 1, ['reason' => 'Revised']);
        $this->expectExceptionObject(WorkflowExecutionException::executionNotWaiting($executionId));
        static::getContainer()->get(WorkflowService::class)->signal($instanceId, $executionId, new WorkflowSignal('example:callback'), $this->context);
    }

    public function testDisabledOperationsAreHiddenAndRejectedWithoutAuditWrites(): void
    {
        $instanceId = $this->start('countersign', operations: []);
        $task = $this->todo($instanceId, 0);
        $this->actors[0]->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        $detail = json_decode((string) $this->actors[0]->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
        static::assertSame([], $detail['tasks'][0]['availableActions']);
        $before = $this->tasks()->historyOf($instanceId, $this->context)->count();

        foreach (['transfer', 'add-sign'] as $action) {
            $this->participate($task, 0, $action, 2, 403);
        }
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => 'Revise']);
        static::assertSame(403, $this->actors[0]->getResponse()->getStatusCode());
        static::assertCount($before, $this->tasks()->historyOf($instanceId, $this->context));
        static::assertCount(2, $this->tasks()->forInstance($instanceId, $this->context));
        static::assertTrue($this->instance($instanceId)->isRunning());
        static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());
    }

    public function testPluginOperationUsesTheLockedPolicyAndRecordsAnAuditEntry(): void
    {
        $hooks = EventHookDispatcher::fromContainer(static::getContainer());
        $hooks->on(ApprovalOperationRegistrationEvent::class, static function (ApprovalOperationRegistrationEvent $event): void {
            $event->add('example:consult');
        });
        $executions = [];
        $hooks->on(ApprovalTaskOperationExtension::onPre(), static function (ApprovalTaskOperationExtension $event) use (&$executions): void {
            $executions[] = [$event->action, $event->task->round, $event->payload];
            $event->result = true;
            $event->stopPropagation();
        });
        $instanceId = $this->start('countersign', operations: ['example:consult']);
        $task = $this->todo($instanceId, 0);
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/operation', [
            'action' => 'example:consult', 'comment' => 'Ask the specialist', 'payload' => ['topic' => 'Compliance'],
        ]);
        static::assertSame(204, $this->actors[0]->getResponse()->getStatusCode());
        static::assertSame([['example:consult', 1, ['topic' => 'Compliance']]], $executions);
        $history = $this->tasks()->historyOf($instanceId, $this->context);
        static::assertCount(1, $history);
        $entry = $history->first();
        static::assertNotNull($entry);
        static::assertSame('operation', $entry->decision);
        static::assertSame('example:consult', $entry->ext['operation'] ?? null);
        static::assertSame('Ask the specialist', $entry->comment);

        $hooks->on(ApprovalTaskActionsEvent::class, static function (ApprovalTaskActionsEvent $event): void {
            $event->deny('example:consult');
        });
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/operation', [
            'action' => 'example:consult', 'comment' => 'Try again',
        ]);
        static::assertSame(403, $this->actors[0]->getResponse()->getStatusCode());
        static::assertCount(1, $executions);
        static::assertCount(1, $this->tasks()->historyOf($instanceId, $this->context));
    }

    public function testPluginFailureRollsBackItsWritesAndCreatesNoAudit(): void
    {
        $hooks = EventHookDispatcher::fromContainer(static::getContainer());
        $hooks->on(ApprovalOperationRegistrationEvent::class, static function (ApprovalOperationRegistrationEvent $event): void {
            $event->add('example:consult');
        });
        $instanceId = $this->start('countersign', operations: ['example:consult']);
        $task = $this->todo($instanceId, 0);
        $before = $task->ext;
        $hooks->on(ApprovalTaskOperationExtension::onPre(), static function (ApprovalTaskOperationExtension $event): void {
            static::getContainer()->get('workflow_task.repository')->update([[
                'id' => $event->task->id, 'ext' => ['example:changed' => true],
            ]], $event->execution->context());
            throw new \RuntimeException('Plugin validation failed.');
        });
        $this->expectExceptionObject(new \RuntimeException('Plugin validation failed.'));
        try {
            static::getContainer()->get(ApprovalWorkflowService::class)->executeOperation($task->id, 'example:consult', [], 'Consult a specialist', $this->context);
        } finally {
            static::assertSame($before, $this->todo($instanceId, 0)->ext);
            static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
            static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());
        }
    }

    public function testDeclaredOperationWithoutAnExecutionHandlerIsRejected(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalOperationRegistrationEvent::class, static function (ApprovalOperationRegistrationEvent $event): void {
            $event->add('example:consult');
        });
        $instanceId = $this->start('countersign', operations: ['example:consult']);
        $task = $this->todo($instanceId, 0);
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/operation', [
            'action' => 'example:consult', 'comment' => 'Consult a specialist',
        ]);
        static::assertSame(403, $this->actors[0]->getResponse()->getStatusCode());
        static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
    }

    public function testPluginOperationRejectsAnExplicitNullPayload(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/operation', [
            'action' => 'example:consult', 'comment' => 'Consult a specialist', 'payload' => null,
        ]);
        static::assertSame(400, $this->actors[0]->getResponse()->getStatusCode());
    }

    private function returnTask(WorkflowTaskEntity $task): void
    {
        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/return', ['comment' => 'Please revise the request']);
        static::assertSame(204, $this->actors[0]->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, string> $formData
     */
    private function resubmit(string $instanceId, int $actor, int $round, array $formData, int $status = 204): void
    {
        $this->actors[$actor]->jsonRequest('POST', '/api/_action/workflow/instance/' . $instanceId . '/resubmit', [
            'round' => $round, 'formVersion' => 0, 'formData' => $formData, 'comment' => 'Additional explanation',
        ]);
        static::assertSame($status, $this->actors[$actor]->getResponse()->getStatusCode());
    }

    /**
     * @param list<string>|null $operations
     */
    private function start(string $mode, bool $withCopy = false, ?array $operations = null): string
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $definitionId = $service->createDefinition([
            'code' => 'participation-' . Uuid::randomHex(), 'name' => 'Participation',
            'graphData' => ['nodes' => [
                ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => [['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => true]]]],
                ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                    'name' => 'Review', 'approvalStage' => true, 'assigneeType' => 'user',
                    'assigneeValue' => \array_slice($this->userIds, 0, 2), 'countersignType' => $mode,
                    ...($operations !== null ? ['approvalOperations' => $operations] : []),
                ]],
                ['id' => 'end', 'type' => 'snaker:end'],
            ], 'edges' => [
                ['id' => 'start-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
                ['id' => 'review-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
            ]],
        ], $this->context);
        if ($withCopy) {
            $graph = $service->loadDefinition($definitionId, $this->context)->graph();
            $graph['nodes'][] = ['id' => 'copy', 'type' => 'snaker:copy', 'properties' => ['name' => 'Notify', 'userIdList' => [$this->userIds[3]]]];
            $graph['edges'][0]['targetNodeId'] = 'copy';
            $graph['edges'][] = ['id' => 'copy-review', 'sourceNodeId' => 'copy', 'targetNodeId' => 'review'];
            $service->saveDefinition($definitionId, $graph, null, $this->context);
        }
        $service->publishDefinition($definitionId, $this->context);

        return $service->start($definitionId, ['reason' => 'Original request'], 'Participation', $this->context);
    }

    private function participate(WorkflowTaskEntity $task, int $actor, string $action, int $target, int $status = 204): void
    {
        $this->actors[$actor]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/participation', [
            'action' => $action, 'targetUserId' => $this->userIds[$target], 'comment' => 'Specialist review',
        ]);
        static::assertSame($status, $this->actors[$actor]->getResponse()->getStatusCode(), json_encode(json_decode((string) $this->actors[$actor]->getResponse()->getContent(), true)['errors'][0]['detail'] ?? null, \JSON_THROW_ON_ERROR));
    }

    private function todo(string $instanceId, int $actor): WorkflowTaskEntity
    {
        foreach ($this->tasks()->forInstance($instanceId, $this->context) as $task) {
            if ($task->assigneeId === $this->userIds[$actor] && $task->isTodo()) {
                return $task;
            }
        }
        static::fail('Expected an actionable task for actor ' . $actor);
    }

    private function tasks(): WorkflowTaskFinder
    {
        return static::getContainer()->get(WorkflowTaskFinder::class);
    }

    private function instance(string $id): WorkflowInstanceEntity
    {
        return static::getContainer()->get(ApprovalWorkflowService::class)->instanceDetail($id, $this->context);
    }
}
