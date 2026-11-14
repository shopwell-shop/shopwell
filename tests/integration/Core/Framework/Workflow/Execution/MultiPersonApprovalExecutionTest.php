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
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;

/**
 * @internal
 */
#[Package('framework')]
class MultiPersonApprovalExecutionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @return iterable<string, array{int}>
     */
    public static function participantCounts(): iterable
    {
        yield 'two approvers' => [2];
        yield 'three approvers' => [3];
    }

    #[DataProvider('participantCounts')]
    public function testAllWaitsForEveryApproverAndFinishesOnlyOnce(int $count): void
    {
        [$manager, $approvers, $userIds] = $this->participants($count);
        $instanceId = $this->start($manager, $this->publish($manager, $userIds, 'core:all'));
        $tasks = $this->tasksByAssignee($instanceId);
        static::assertCount($count, $tasks);

        foreach ($approvers as $index => $approver) {
            $taskId = $tasks[$userIds[$index]];
            $approver->request('GET', '/api/_action/workflow/task/todo');
            static::assertSame([$taskId], array_column($this->response($approver)['data'], 'id'));
            $approver->jsonRequest('POST', '/api/_action/workflow/task/' . $taskId . '/approve', ['comment' => 'Approved']);
            static::assertSame(204, $approver->getResponse()->getStatusCode(), $this->errors($approver));
            $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
            static::assertSame($index === $count - 1 ? 'AGREE' : 'RUNNING', $this->response($manager)['data']['status']);
        }

        $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[0]] . '/approve', []);
        static::assertSame(409, $approvers[0]->getResponse()->getStatusCode());
        $finder = static::getContainer()->get(WorkflowTaskFinder::class);
        static::assertCount($count, $finder->historyOf($instanceId, Context::createDefaultContext()));
        static::assertSame($count, $finder->forInstance($instanceId, Context::createDefaultContext())->countByState(WorkflowTaskStates::AGREED));
        $this->assertNoTodos($approvers);
        $this->assertSingleFinish($instanceId);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function firstDecisions(): iterable
    {
        yield 'all ends on the first rejection' => ['core:all', 'reject', 'REJECT'];
        yield 'any ends on the first approval' => ['core:any', 'approve', 'AGREE'];
        yield 'any ends on the first rejection' => ['core:any', 'reject', 'REJECT'];
    }

    #[DataProvider('firstDecisions')]
    public function testResolvedNodeCancelsOtherTasksAndRejectsStaleDecisions(string $mode, string $decision, string $status): void
    {
        [$manager, $approvers, $userIds] = $this->participants(3);
        $instanceId = $this->start($manager, $this->publish($manager, $userIds, $mode));
        $tasks = $this->tasksByAssignee($instanceId);
        // Deliberately decide from the middle of the configured list: submission order wins.
        $approvers[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[1]] . '/' . $decision, ['comment' => 'Decision']);
        static::assertSame(204, $approvers[1]->getResponse()->getStatusCode(), $this->errors($approvers[1]));
        $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame($status, $this->response($manager)['data']['status']);
        $finder = static::getContainer()->get(WorkflowTaskFinder::class);
        $allTasks = $finder->forInstance($instanceId, Context::createDefaultContext());
        static::assertSame(2, $allTasks->countByState(WorkflowTaskStates::CANCELED));
        foreach ($allTasks as $task) {
            static::assertNotNull($task->completedAt);
        }
        $this->assertNoTodos($approvers);
        foreach ([$approvers[0], $approvers[1]] as $index => $approver) {
            $approver->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[$index]] . '/approve', []);
            static::assertSame(409, $approver->getResponse()->getStatusCode());
            static::assertSame(WorkflowExecutionException::TASK_ALREADY_HANDLED, $this->response($approver)['errors'][0]['code']);
        }
        static::assertCount(1, $finder->historyOf($instanceId, Context::createDefaultContext()));
        $this->assertSingleFinish($instanceId);
    }

    public function testSequentialStagesCreateFutureTasksOnlyAfterApprovalAndKeepSnapshot(): void
    {
        [$manager, $approvers, $userIds] = $this->participants(3);
        $releaseId = $this->publish($manager, $userIds, 'core:any', sequential: true);
        $instanceId = $this->start($manager, $releaseId);
        $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        $detail = $this->response($manager)['data'];
        static::assertSame(['active', 'pending'], array_column($detail['approvalStages'], 'status'));
        static::assertSame([], $detail['approvalStages'][1]['tasks']);
        $release = static::getContainer()->get(Connection::class)->fetchAssociative('SELECT draft_id, document FROM workflow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        static::assertIsArray($release);
        $draftId = Uuid::fromBytesToHex($release['draft_id']);
        $changed = json_decode($release['document'], true, flags: \JSON_THROW_ON_ERROR);
        $changed['nodes'][4]['config']['label'] = 'Changed reviewer';
        $changed['nodes'][4]['config']['assignee'] = ['source' => 'core:users', 'config' => ['userIds' => [$userIds[0], $userIds[1]]]];
        $changed['nodes'][4]['config']['approval']['mode'] = 'core:all';
        $manager->jsonRequest('PUT', '/api/_action/workflow/draft/' . $draftId, ['name' => 'Changed approval', 'document' => $changed, 'expectedRevision' => 1]);
        static::assertSame(200, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draftId . '/publish', ['expectedRevision' => 2, 'publisher' => 'core:approval']);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        static::assertCount(2, $detail['approvalTasks']);
        static::assertSame($userIds[2], $detail['approvalStages'][1]['participants'][0]['id']);
        $approvers[2]->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(403, $approvers[2]->getResponse()->getStatusCode());
        $tasks = $this->tasksByAssignee($instanceId);
        $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[0]] . '/approve', []);
        static::assertSame(204, $approvers[0]->getResponse()->getStatusCode(), $this->errors($approvers[0]));
        $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        $after = $this->response($manager)['data'];
        static::assertSame(['approved', 'active'], array_column($after['approvalStages'], 'status'));
        static::assertSame($detail['graph'], $after['graph']);
        $states = array_column($after['approvalStages'][0]['tasks'], 'state');
        sort($states);
        static::assertSame(['agreed', 'canceled'], $states);
        $approvers[1]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[1]] . '/approve', []);
        static::assertSame(409, $approvers[1]->getResponse()->getStatusCode());
        $tasks = $this->tasksByAssignee($instanceId);
        $approvers[2]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[2]] . '/approve', []);
        static::assertSame(204, $approvers[2]->getResponse()->getStatusCode(), $this->errors($approvers[2]));
        $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(['approved', 'approved'], array_column($this->response($manager)['data']['approvalStages'], 'status'));
        $this->assertSingleFinish($instanceId);
    }

    public function testAllThenAnyThenSingleAdvanceIndependently(): void
    {
        [$manager, $approvers, $userIds] = $this->participants(3);
        $instanceId = $this->start($manager, $this->publish($manager, $userIds, 'core:all', sequential: true, threeStages: true));
        foreach ([0, 1, 1, 2] as $step => $actor) {
            $tasks = $this->tasksByAssignee($instanceId);
            $approvers[$actor]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[$actor]] . '/approve', []);
            static::assertSame(204, $approvers[$actor]->getResponse()->getStatusCode(), $this->errors($approvers[$actor]));
            $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
            static::assertSame([
                ['active', 'pending', 'pending'],
                ['approved', 'active', 'pending'],
                ['approved', 'approved', 'active'],
                ['approved', 'approved', 'approved'],
            ][$step], array_column($this->response($manager)['data']['approvalStages'], 'status'));
        }
        $this->assertSingleFinish($instanceId);
    }

    public function testCancelAfterPartialCountersignDoesNotApproveTheStage(): void
    {
        [$manager, $approvers, $userIds] = $this->participants(3);
        $instanceId = $this->start($manager, $this->publish($manager, $userIds, 'core:all', sequential: true));
        $tasks = $this->tasksByAssignee($instanceId);
        $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[0]] . '/approve', []);
        static::assertSame(204, $approvers[0]->getResponse()->getStatusCode(), $this->errors($approvers[0]));
        $manager->jsonRequest('POST', '/api/_action/workflow/instance/' . $instanceId . '/cancel', ['reason' => 'Withdraw']);
        static::assertSame(204, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(['skipped', 'skipped'], array_column($this->response($manager)['data']['approvalStages'], 'status'));
    }

    public function testSequentialRejectionSkipsFutureStagesWithoutCreatingTasks(): void
    {
        [$manager, $approvers, $userIds] = $this->participants(3);
        $instanceId = $this->start($manager, $this->publish($manager, $userIds, 'core:all', sequential: true));
        $tasks = $this->tasksByAssignee($instanceId);
        $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[0]] . '/reject', ['comment' => 'Rejected']);
        static::assertSame(204, $approvers[0]->getResponse()->getStatusCode(), $this->errors($approvers[0]));
        $manager->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        $detail = $this->response($manager)['data'];
        static::assertSame(['rejected', 'skipped'], array_column($detail['approvalStages'], 'status'));
        static::assertSame([], $detail['approvalStages'][1]['tasks']);
        static::assertCount(2, $detail['approvalTasks']);
    }

    public function testDecisionPermissionDoesNotAllowActingForAnotherApprover(): void
    {
        [$manager, $approvers, $userIds] = $this->participants(2);
        $instanceId = $this->start($manager, $this->publish($manager, $userIds, 'core:all'));
        $tasks = $this->tasksByAssignee($instanceId);
        foreach (['approve', 'reject'] as $decision) {
            $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[1]] . '/' . $decision, ['comment' => 'Another user']);
            static::assertSame(404, $approvers[0]->getResponse()->getStatusCode());
            static::assertSame(WorkflowExecutionException::TASK_NOT_YOURS, $this->response($approvers[0])['errors'][0]['code']);
        }
        static::assertSame(2, static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, Context::createDefaultContext())->countByState(WorkflowTaskStates::TODO));
        static::assertCount(0, static::getContainer()->get(WorkflowTaskFinder::class)->historyOf($instanceId, Context::createDefaultContext()));
    }

    public function testDisabledMemberDoesNotSilentlyReducePublishedQuorum(): void
    {
        [$manager, , $userIds] = $this->participants(3);
        $releaseId = $this->publish($manager, $userIds, 'core:all');
        static::getContainer()->get('user.repository')->update([['id' => $userIds[1], 'active' => false]], Context::createDefaultContext());
        $manager->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $releaseId, 'formData' => []]);
        static::assertSame(400, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        static::assertSame(WorkflowExecutionException::NO_ASSIGNEE, $this->response($manager)['errors'][0]['code']);
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM workflow_instance WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]));
        static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());
    }

    public function testFailureEnteringNextNodeRollsBackDecisionSiblingCancellationAndEvents(): void
    {
        [, $approvers, $userIds] = $this->participants(3);
        $source = new AdminApiSource($userIds[0]);
        $source->setIsAdmin(true);
        $context = new Context($source);
        $service = static::getContainer()->get(ApprovalWorkflowService::class);
        $definitionId = $service->createDefinition([
            'code' => 'rollback-' . Uuid::randomHex(), 'name' => 'Rollback approval', 'graphData' => [
                'nodes' => [
                    ['id' => 'start', 'type' => 'snaker:start', 'properties' => []],
                    ['id' => 'first', 'type' => 'snaker:task', 'properties' => ['name' => 'First approval', 'assigneeType' => 'user', 'assigneeValue' => \array_slice($userIds, 0, 2), 'countersignType' => 'firstDecision']],
                    ['id' => 'second', 'type' => 'snaker:task', 'properties' => ['name' => 'Second approval', 'assigneeType' => 'user', 'assigneeValue' => [$userIds[2]], 'countersignType' => 'single']],
                    ['id' => 'end', 'type' => 'snaker:end', 'properties' => []],
                ], 'edges' => [
                    ['id' => 'start-first', 'sourceNodeId' => 'start', 'targetNodeId' => 'first', 'properties' => []],
                    ['id' => 'first-second', 'sourceNodeId' => 'first', 'targetNodeId' => 'second', 'properties' => []],
                    ['id' => 'second-end', 'sourceNodeId' => 'second', 'targetNodeId' => 'end', 'properties' => []],
                ],
            ],
        ], $context);
        $service->publishDefinition($definitionId, $context);
        $instanceId = $service->start($definitionId, [], 'Rollback approval', $context);
        $tasks = $this->tasksByAssignee($instanceId);
        $approvers[0]->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $approvers[0]->getResponse()->getStatusCode(), $this->errors($approvers[0]));
        $detail = $this->response($approvers[0])['data'];
        static::assertSame([
            'nodeIds' => ['start', 'first', 'second', 'end'],
            'edgeIds' => ['start-first', 'first-second', 'second-end'],
        ], $detail['plannedPath']);
        static::assertSame([
            'nodeIds' => ['start', 'first'],
            'edgeIds' => ['start-first'],
        ], $detail['selectedPath']);
        $activationsBeforeDecision = static::getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT id, node_id, sequence, status, waiting_for, ended_at FROM workflow_node_execution WHERE instance_id = :id ORDER BY sequence',
            ['id' => Uuid::fromHexToBytes($instanceId)],
        );
        static::assertSame(['start', 'first'], array_column($activationsBeforeDecision, 'node_id'));
        static::assertSame(['completed', 'waiting'], array_column($activationsBeforeDecision, 'status'));
        static::getContainer()->get('user.repository')->update([['id' => $userIds[2], 'active' => false]], Context::createDefaultContext());

        $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[0]] . '/approve', []);
        static::assertSame(400, $approvers[0]->getResponse()->getStatusCode(), $this->errors($approvers[0]));
        static::assertSame(WorkflowExecutionException::NO_ASSIGNEE, $this->response($approvers[0])['errors'][0]['code']);
        $finder = static::getContainer()->get(WorkflowTaskFinder::class);
        static::assertSame(2, $finder->forInstance($instanceId, $context)->countByState(WorkflowTaskStates::TODO));
        static::assertCount(0, $finder->historyOf($instanceId, $context));
        static::assertSame('first', $service->loadInstance($instanceId, $context)->currentNodeId);
        static::assertSame($activationsBeforeDecision, static::getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT id, node_id, sequence, status, waiting_for, ended_at FROM workflow_node_execution WHERE instance_id = :id ORDER BY sequence',
            ['id' => Uuid::fromHexToBytes($instanceId)],
        ));
        static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());

        static::getContainer()->get('user.repository')->update([['id' => $userIds[2], 'active' => true]], Context::createDefaultContext());
        $approvers[0]->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[$userIds[0]] . '/approve', []);
        static::assertSame(204, $approvers[0]->getResponse()->getStatusCode(), $this->errors($approvers[0]));
        static::assertCount(1, $finder->historyOf($instanceId, $context));
        static::assertSame(1, $finder->forInstance($instanceId, $context)->countByState(WorkflowTaskStates::CANCELED));
        static::assertSame('second', $service->loadInstance($instanceId, $context)->currentNodeId);
        static::assertTrue(static::getContainer()->get(WorkflowEventQueue::class)->isEmpty());
    }

    /**
     * @return array{TestBrowser, list<TestBrowser>, list<string>}
     */
    private function participants(int $count): array
    {
        $manager = $this->createClient(permissions: ['workflow:read', 'workflow:create', 'workflow:update', 'workflow:publish', 'workflow_instance:access', 'workflow_instance:start', 'user:read', 'state_machine_state:read']);
        $approvers = [];
        $userIds = [];
        for ($index = 0; $index < $count; ++$index) {
            $approvers[] = $this->createClient(permissions: ['workflow_instance:access', 'workflow_task:decide']);
            $userId = Uuid::fromBytesToHex(static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM user WHERE username = :username', ['username' => $this->apiUsernames[array_key_last($this->apiUsernames) ?? 0]]));
            static::getContainer()->get('user.repository')->update([['id' => $userId, 'email' => $userId . '@example.com']], Context::createDefaultContext());
            $userIds[] = $userId;
        }

        return [$manager, $approvers, $userIds];
    }

    /**
     * @param list<string> $userIds
     */
    private function publish(TestBrowser $manager, array $userIds, string $mode, bool $sequential = false, bool $threeStages = false): string
    {
        $draftId = Uuid::randomHex();
        $document = [
            'schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [
                ['id' => 'start', 'type' => 'core:start', 'typeVersion' => 1, 'config' => ['inputType' => 'core:approval-request']],
                ['id' => 'approval', 'type' => 'core:approval', 'typeVersion' => 1, 'config' => [
                    'assignee' => ['source' => 'core:users', 'config' => ['userIds' => $sequential ? \array_slice($userIds, 0, 2) : $userIds]],
                    'approval' => ['mode' => $mode, 'config' => []],
                ]],
                ['id' => 'end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
                ['id' => 'rejected-end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
            ], 'connections' => [
                ['id' => 'start-approval', 'source' => 'start', 'sourcePort' => 'out', 'target' => 'approval', 'targetPort' => 'in'],
                ['id' => 'approved', 'source' => 'approval', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'],
                ['id' => 'rejected', 'source' => 'approval', 'sourcePort' => 'rejected', 'target' => 'rejected-end', 'targetPort' => 'in'],
            ],
        ];
        if ($sequential) {
            $document['nodes'][] = ['id' => 'second', 'type' => 'core:approval', 'typeVersion' => 1, 'config' => [
                'label' => 'Finance', 'description' => 'Final check',
                'assignee' => ['source' => 'core:user', 'config' => ['userId' => $userIds[2]]],
                'approval' => ['mode' => 'core:single', 'config' => []],
            ]];
            $document['nodes'][] = ['id' => 'second-rejected-end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []];
            $document['connections'][1]['target'] = 'second';
            $document['connections'][] = ['id' => 'second-approved', 'source' => 'second', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'];
            $document['connections'][] = ['id' => 'second-rejected', 'source' => 'second', 'sourcePort' => 'rejected', 'target' => 'second-rejected-end', 'targetPort' => 'in'];
        }
        if ($threeStages && $sequential) {
            $third = $document['nodes'][4];
            $third['id'] = 'third';
            $document['nodes'][4]['config']['assignee'] = ['source' => 'core:users', 'config' => ['userIds' => [$userIds[1], $userIds[2]]]];
            $document['nodes'][4]['config']['approval']['mode'] = 'core:any';
            $document['nodes'][] = $third;
            $document['nodes'][] = ['id' => 'third-rejected-end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []];
            $document['connections'][3]['target'] = 'third';
            $document['connections'][] = ['id' => 'third-approved', 'source' => 'third', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'];
            $document['connections'][] = ['id' => 'third-rejected', 'source' => 'third', 'sourcePort' => 'rejected', 'target' => 'third-rejected-end', 'targetPort' => 'in'];
        }
        $manager->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $draftId, 'name' => 'Team approval', 'document' => $document]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draftId . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));

        return $this->response($manager)['id'];
    }

    private function start(TestBrowser $manager, string $releaseId): string
    {
        $manager->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $releaseId, 'formData' => []]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errors($manager));

        return $this->response($manager)['data']['id'];
    }

    /**
     * @return array<string, string>
     */
    private function tasksByAssignee(string $instanceId): array
    {
        $tasks = [];
        foreach (static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, Context::createDefaultContext()) as $task) {
            if (!isset($tasks[$task->assigneeId]) || $task->isTodo()) {
                $tasks[$task->assigneeId] = $task->id;
            }
        }

        return $tasks;
    }

    /**
     * @param list<TestBrowser> $approvers
     */
    private function assertNoTodos(array $approvers): void
    {
        foreach ($approvers as $approver) {
            $approver->request('GET', '/api/_action/workflow/task/todo');
            static::assertSame(200, $approver->getResponse()->getStatusCode(), $this->errors($approver));
            static::assertSame([], $this->response($approver)['data']);
        }
    }

    private function assertSingleFinish(string $instanceId): void
    {
        static::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM state_machine_history WHERE entity_name = :entity AND referenced_id = :id',
            ['entity' => 'workflow_instance', 'id' => Uuid::fromHexToBytes($instanceId)],
        ));
    }

    private function errors(TestBrowser $browser): string
    {
        $body = json_decode((string) $browser->getResponse()->getContent(), true);

        return json_encode($body['errors'] ?? [], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function response(TestBrowser $browser): array
    {
        return json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
