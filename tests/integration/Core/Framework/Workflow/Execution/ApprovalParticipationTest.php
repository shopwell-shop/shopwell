<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalOperationRegistrationEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Extension\ApprovalTaskParticipationExtension;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\System\Organization\OrganizationDefaults;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalParticipationTest extends TestCase
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
            $this->actors[] = $this->createClient(permissions: ['workflow_instance:access', 'workflow_task:decide']);
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

    public function testTransferPreservesHistoryAndSequentialPosition(): void
    {
        $instanceId = $this->start('orderSign');
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'transfer', 2);
        $replacement = $this->todo($instanceId, 2);
        static::assertNotSame($original->id, $replacement->id);
        static::assertSame($original->sequence, $replacement->sequence);
        static::assertSame($original->assigneeSnapshot(), $replacement->assigneeSnapshot());
        static::assertSame(WorkflowTaskStates::CANCELED, $this->tasks()->forInstance($instanceId, $this->context)->get($original->id)?->state());

        $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $original->id . '/approve', []);
        static::assertSame(409, $this->actors[0]->getResponse()->getStatusCode());
        $this->decide($replacement, 2);
        static::assertTrue($this->instance($instanceId)->isRunning());
        $this->decide($this->todo($instanceId, 1), 1);
        static::assertFalse($this->instance($instanceId)->isRunning());
        static::assertSame(['transfer', 'agree', 'agree'], array_values(array_map(
            static fn ($entry) => $entry->decision,
            $this->tasks()->historyOf($instanceId, $this->context)->getElements(),
        )));
        $this->actors[0]->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $this->actors[0]->getResponse()->getStatusCode());
    }

    public static function modes(): iterable
    {
        yield 'all' => ['countersign'];
        yield 'first decision' => ['firstDecision'];
        yield 'any agreement' => ['orSign'];
        yield 'sequential' => ['orderSign'];
    }

    #[DataProvider('modes')]
    public function testAddedReviewerCannotBeSkipped(string $mode): void
    {
        $instanceId = $this->start($mode);
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'add-sign', 2);
        $this->decide($original, 0);
        if (\in_array($mode, ['countersign', 'orderSign'], true)) {
            $this->decide($this->todo($instanceId, 1), 1);
        }
        static::assertTrue($this->instance($instanceId)->isRunning());
        static::assertSame(1, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
        $this->decide($this->todo($instanceId, 2), 2);
        static::assertFalse($this->instance($instanceId)->isRunning());
    }

    public function testAddedReviewerMayAgreeBeforeOriginalSequence(): void
    {
        $instanceId = $this->start('orderSign');
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'add-sign', 2);
        $this->decide($this->todo($instanceId, 2), 2);
        static::assertSame(1, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
        $this->decide($original, 0);
        $this->decide($this->todo($instanceId, 1), 1);
        static::assertFalse($this->instance($instanceId)->isRunning());
    }

    public function testAddedReviewerRejectionVetoesAnyAgreement(): void
    {
        $instanceId = $this->start('orSign');
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'add-sign', 2);
        $this->decide($original, 0);
        $this->decide($this->todo($instanceId, 2), 2, 'reject');
        static::assertSame('rejected', $this->instance($instanceId)->state());
        static::assertSame(0, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
    }

    public function testMultipleAddedReviewersRemainRequired(): void
    {
        $instanceId = $this->start('firstDecision');
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'add-sign', 2);
        $this->participate($original, 0, 'add-sign', 3);
        $this->decide($original, 0);
        $this->decide($this->todo($instanceId, 2), 2);
        static::assertTrue($this->instance($instanceId)->isRunning());
        $this->decide($this->todo($instanceId, 3), 3);
        static::assertFalse($this->instance($instanceId)->isRunning());
    }

    public function testUnauthorizedDuplicateAndIneligibleTargetsDoNotChangeTasks(): void
    {
        $instanceId = $this->start('countersign');
        $original = $this->todo($instanceId, 0);
        foreach ([['transfer', 1, 2, 404], ['add-sign', 0, 1, 400], ['transfer', 0, 0, 400]] as [$action, $actor, $target, $status]) {
            $this->participate($original, $actor, $action, $target, $status);
        }
        static::getContainer()->get('user.repository')->update([['id' => $this->userIds[2], 'active' => false]], $this->context);
        $this->participate($original, 0, 'transfer', 2, 400);
        static::assertSame(2, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
        static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
    }

    public function testTransferredAddedReviewerKeepsTheirMandatoryVote(): void
    {
        $instanceId = $this->start('firstDecision');
        $original = $this->todo($instanceId, 0);
        $this->participate($original, 0, 'add-sign', 2);
        $this->participate($this->todo($instanceId, 2), 2, 'transfer', 3);
        $this->decide($original, 0);
        static::assertTrue($this->instance($instanceId)->isRunning());
        $this->decide($this->todo($instanceId, 3), 3, 'reject');
        static::assertSame('rejected', $this->instance($instanceId)->state());
    }

    public function testMissingReasonAndMalformedTargetAreRejected(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        foreach ([['transfer', $this->userIds[2], ' '], ['add-sign', 'invalid', 'Expert']] as [$action, $target, $comment]) {
            $this->actors[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/participation', [
                'action' => $action, 'targetUserId' => $target, 'comment' => $comment,
            ]);
            static::assertSame(400, $this->actors[0]->getResponse()->getStatusCode());
        }
        static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
    }

    public function testRepeatedTransferDoesNotCreateAnotherReplacement(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        $this->participate($task, 0, 'transfer', 2);
        $this->participate($task, 0, 'transfer', 3, 409);
        static::assertCount(3, $this->tasks()->forInstance($instanceId, $this->context));
        static::assertCount(1, $this->tasks()->historyOf($instanceId, $this->context));
    }

    public function testOldRoundCannotChangeParticipantsAtTheSameNode(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        static::getContainer()->get('workflow_instance.repository')->update([
            ['id' => $instanceId, 'round' => 2],
        ], Context::createDefaultContext());

        $this->participate($task, 0, 'add-sign', 2, 409);
        static::assertCount(2, $this->tasks()->forInstance($instanceId, $this->context));
        static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
    }

    public function testPluginFailureRollsBackReplacementHistoryAndNotifications(): void
    {
        $instanceId = $this->start('countersign');
        $task = $this->todo($instanceId, 0);
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalTaskParticipationExtension::onPost(), static function (): void {
            throw new \RuntimeException('Plugin refused transfer');
        });
        $this->expectExceptionObject(new \RuntimeException('Plugin refused transfer'));
        try {
            static::getContainer()->get(ApprovalWorkflowService::class)->participate($task->id, 'transfer', $this->userIds[2], 'Expert', $this->context);
        } finally {
            static::assertSame(2, $this->tasks()->forInstance($instanceId, $this->context)->countByState(WorkflowTaskStates::TODO));
            static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
            static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());
        }
    }

    public function testPluginCanReplaceAnOperationInsideTheValidatedBoundary(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalOperationRegistrationEvent::class, static function (ApprovalOperationRegistrationEvent $event): void {
            $event->add('vendor:request-expert');
        });
        $instanceId = $this->start('countersign', operations: ['vendor:request-expert']);
        $task = $this->todo($instanceId, 0);
        $received = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(ApprovalTaskParticipationExtension::onPre(), static function (ApprovalTaskParticipationExtension $extension) use (&$received): void {
            $received = $extension;
            $extension->result = $extension->task->id;
            $extension->stopPropagation();
        });
        $this->participate($task, 0, 'vendor:request-expert', 2);
        static::assertInstanceOf(ApprovalTaskParticipationExtension::class, $received);
        static::assertSame($task->id, $received->task->id);
        static::assertSame($this->userIds[2], $received->targetUserId);
        static::assertSame('vendor:request-expert', $received->action);
        static::assertCount(2, $this->tasks()->forInstance($instanceId, $this->context));
        static::assertCount(0, $this->tasks()->historyOf($instanceId, $this->context));
    }

    /**
     * @param list<string>|null $operations
     */
    private function start(string $mode, ?array $operations = null): string
    {
        $service = static::getContainer()->get(WorkflowService::class);
        $definitionId = $service->createDefinition([
            'code' => 'participation-' . Uuid::randomHex(), 'name' => 'Participation',
            'graphData' => ['nodes' => [
                ['id' => 'start', 'type' => 'snaker:start'],
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
        $service->publishDefinition($definitionId, $this->context);

        return $service->start($definitionId, [], 'Participation', $this->context);
    }

    private function participate(WorkflowTaskEntity $task, int $actor, string $action, int $target, int $status = 204): void
    {
        $this->actors[$actor]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/participation', [
            'action' => $action, 'targetUserId' => $this->userIds[$target], 'comment' => 'Specialist review',
        ]);
        static::assertSame($status, $this->actors[$actor]->getResponse()->getStatusCode(), json_encode(json_decode((string) $this->actors[$actor]->getResponse()->getContent(), true)['errors'][0]['detail'] ?? null, \JSON_THROW_ON_ERROR));
    }

    private function decide(WorkflowTaskEntity $task, int $actor, string $action = 'approve'): void
    {
        $this->actors[$actor]->jsonRequest('POST', '/api/_action/workflow/task/' . $task->id . '/' . $action, ['comment' => 'Reviewed']);
        static::assertSame(204, $this->actors[$actor]->getResponse()->getStatusCode(), json_encode(json_decode((string) $this->actors[$actor]->getResponse()->getContent(), true)['errors'][0]['detail'] ?? null, \JSON_THROW_ON_ERROR));
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
