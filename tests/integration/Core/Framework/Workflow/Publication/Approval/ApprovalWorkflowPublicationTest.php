<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Publication\Approval;

use Doctrine\DBAL\Connection;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Permission\WorkflowDefaultRolesEvent;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalWorkflowPublicationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testPublishedWorkflowStartsAndApprovesWithActionPermissionsOnly(): void
    {
        $browser = $this->getBrowser(permissions: $this->permissions());
        $releaseId = $this->publish($browser, $this->actorId());
        $browser->request('GET', '/api/_action/workflow/definition');
        $this->assertResponseSchema($browser, '/_action/workflow/definition');
        $browser->request('GET', '/api/_action/workflow/definition/' . $releaseId);
        $this->assertResponseSchema($browser, '/_action/workflow/definition/{definitionId}');
        $instanceId = $this->start($browser, $releaseId);
        $browser->request('GET', '/api/_action/workflow/instance/mine');
        $this->assertResponseSchema($browser, '/_action/workflow/instance/mine');
        $browser->request('GET', '/api/_action/workflow/task/todo');
        $this->assertResponseSchema($browser, '/_action/workflow/task/todo');
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $tasks = $this->response($browser)['data'];
        static::assertCount(1, $tasks);
        static::assertSame($instanceId, $tasks[0]['instanceId']);
        $browser->jsonRequest('POST', '/api/_action/workflow/task/' . $tasks[0]['id'] . '/approve', ['comment' => 'Approved']);
        static::assertSame(204, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $browser->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $this->assertResponseSchema($browser, '/_action/workflow/instance/{instanceId}');
        $completed = $this->response($browser)['data'];
        static::assertSame('AGREE', $completed['status']);
        static::assertCount(1, $completed['approvalTasks']);
        static::assertSame('agreed', $completed['approvalTasks'][0]['state']);
        static::assertFalse($completed['approvalTasks'][0]['actionable']);
        $browser->request('GET', '/api/_action/workflow/task/done');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $this->assertResponseSchema($browser, '/_action/workflow/task/done');
        static::assertCount(1, $this->response($browser)['data']);
    }

    public function testDefaultParticipantsCannotReadOtherInstancesThroughGenericApi(): void
    {
        $manager = $this->getBrowser(permissions: $this->permissions());
        $privileges = (new WorkflowDefaultRolesEvent())->getPrivileges(WorkflowDefaultRolesEvent::PARTICIPANT);
        static::assertNotContains('workflow_instance:read', $privileges);
        $owner = $this->createClient(permissions: $privileges);
        $actorId = $this->actorId();
        static::getContainer()->get('user.repository')->update([['id' => $actorId, 'email' => $actorId . '@example.com']], Context::createDefaultContext());
        $releaseId = $this->publish($manager, $this->actorId());
        $instanceId = $this->start($owner, $releaseId);
        $owner->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $owner->getResponse()->getStatusCode(), $this->errorSummary($owner));
        static::assertSame($instanceId, $this->response($owner)['data']['id']);

        $stranger = $this->createClient(permissions: $privileges);
        $stranger->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(403, $stranger->getResponse()->getStatusCode());
        foreach ([$owner, $stranger] as $browser) {
            $browser->request('GET', '/api/workflow-instance/' . $instanceId);
            static::assertSame(403, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
            $browser->jsonRequest('POST', '/api/search/workflow-instance', ['ids' => [$instanceId]]);
            static::assertSame(403, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        }
    }

    #[DataProvider('taskLists')]
    public function testTaskListsIncludeInstanceIdentity(string $path, bool $completed): void
    {
        $browser = $this->getBrowser(permissions: $this->permissions());
        $releaseId = $this->publish($browser, $this->actorId());
        $instances = [];
        for ($i = 0; $i < 2; ++$i) {
            $instanceId = $this->start($browser, $releaseId);
            $browser->request('GET', '/api/_action/workflow/instance/' . $instanceId);
            $instances[$instanceId] = $this->response($browser)['data'];
        }
        if ($completed) {
            $browser->request('GET', '/api/_action/workflow/task/todo');
            foreach ($this->response($browser)['data'] as $task) {
                $browser->jsonRequest('POST', '/api/_action/workflow/task/' . $task['id'] . '/approve', []);
                static::assertSame(204, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
            }
        }

        $instanceLookups = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntitySearchedEvent::class, static function (EntitySearchedEvent $event) use (&$instanceLookups): void {
            if ($event->getDefinition()->getEntityName() === 'workflow_instance') {
                $instanceLookups[] = $event->getCriteria()->getIds();
            }
        });
        $browser->request('GET', '/api' . $path);
        $this->assertResponseSchema($browser, $path);
        static::assertCount(1, $instanceLookups, 'List items must share one bulk instance lookup.');
        static::assertEqualsCanonicalizing(array_keys($instances), array_values($instanceLookups[0]));
        $tasks = $this->response($browser)['data'];
        static::assertCount(2, $tasks);
        foreach ($tasks as $task) {
            $instance = $instances[$task['instanceId']];
            static::assertArrayHasKey('title', $task);
            static::assertSame($instance['title'], $task['title']);
            static::assertSame($instance['number'], $task['number']);
            static::assertSame($instance['initiator'], $task['initiator']);
        }
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function taskLists(): iterable
    {
        yield 'pending tasks' => ['/_action/workflow/task/todo', false];
        yield 'completed tasks' => ['/_action/workflow/task/done', true];
    }

    #[DataProvider('paginatedLists')]
    public function testPaginatedListsReportTheTotalBeyondTheCurrentPage(string $path, bool $completed): void
    {
        $browser = $this->getBrowser(permissions: $this->permissions());
        for ($i = 0; $i < 2; ++$i) {
            $releaseId = $this->publish($browser, $this->actorId());
            $this->start($browser, $releaseId);
        }
        if ($completed) {
            $browser->request('GET', '/api/_action/workflow/task/todo');
            $tasks = $this->response($browser)['data'];
            static::assertCount(2, $tasks);
            foreach ($tasks as $task) {
                $browser->jsonRequest('POST', '/api/_action/workflow/task/' . $task['id'] . '/approve', []);
                static::assertSame(204, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
            }
        }

        $browser->request('GET', '/api' . $path . '?limit=1');
        $this->assertResponseSchema($browser, $path);
        $response = $this->response($browser);
        static::assertCount(1, $response['data']);
        static::assertGreaterThanOrEqual(2, $response['meta']['total']);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paginatedLists(): iterable
    {
        yield 'definitions' => ['/_action/workflow/definition', false];
        yield 'my instances' => ['/_action/workflow/instance/mine', false];
        yield 'pending tasks' => ['/_action/workflow/task/todo', false];
        yield 'completed tasks' => ['/_action/workflow/task/done', true];
    }

    public function testConsecutiveStartsCreateDistinctApprovalNumbers(): void
    {
        $browser = $this->getBrowser(permissions: $this->permissions());
        $releaseId = $this->publish($browser, $this->actorId());
        $firstId = $this->start($browser, $releaseId);
        $secondId = $this->start($browser, $releaseId);
        static::assertNotSame($firstId, $secondId);

        $browser->request('GET', '/api/_action/workflow/instance/' . $firstId);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $firstNumber = $this->response($browser)['data']['number'];
        $browser->request('GET', '/api/_action/workflow/instance/' . $secondId);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        static::assertNotSame($firstNumber, $this->response($browser)['data']['number']);
    }

    public function testUnrelatedRoleReceivesNoWorkflowExecutionPermissions(): void
    {
        $id = Uuid::randomHex();
        $repository = static::getContainer()->get('acl_role.repository');
        $repository->create([['id' => $id, 'name' => 'Catalog viewer', 'privileges' => ['product:read']]], Context::createDefaultContext());
        $role = $repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(AclRoleEntity::class, $role);
        static::assertContains('product:read', $role->getPrivileges());
        foreach ($role->getPrivileges() as $privilege) {
            static::assertStringStartsNotWith('workflow_instance:', $privilege);
            static::assertStringStartsNotWith('workflow_task:', $privilege);
        }
        $browser = $this->getBrowser(permissions: ['product:read']);
        $browser->request('GET', '/api/_action/workflow/task/todo');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/_action/workflow/task/' . Uuid::randomHex() . '/approve', []);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/_action/workflow/instance', []);
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testParticipantReadsFormSchemaWithoutDefinitionReadPermission(): void
    {
        $manager = $this->getBrowser(permissions: $this->permissions());
        $participant = $this->createClient(permissions: ['workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide', 'user:read', 'state_machine_state:read']);
        $releaseId = $this->publish($manager, $this->actorId());
        $instanceId = $this->start($participant, $releaseId);

        $participant->request('GET', '/api/_action/workflow/definition/' . $releaseId);
        static::assertSame(403, $participant->getResponse()->getStatusCode());
        $participant->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $participant->getResponse()->getStatusCode(), $this->errorSummary($participant));
        $instance = $this->response($participant)['data'];
        static::assertEquals([['key' => 'reason', 'label' => '申请事由', 'type' => 'text', 'required' => false]], $instance['formSchema']);
        static::assertSame('Customer visit', $instance['formData']['reason']);
        static::assertSame('RUNNING', $instance['status']);
        static::assertSame('todo', $instance['tasks'][0]['state']);
        static::assertTrue($instance['tasks'][0]['actionable']);
        static::assertSame($instance['tasks'], $instance['approvalTasks']);
    }

    public function testOtherActorCannotDecideReassignOrReadTaskAndAssignedActorCanReject(): void
    {
        $owner = $this->getBrowser(permissions: $this->permissions());
        $releaseId = $this->publish($owner, $this->actorId());
        $instanceId = $this->start($owner, $releaseId);
        $owner->request('GET', '/api/_action/workflow/task/todo');
        $taskId = $this->response($owner)['data'][0]['id'];
        $stranger = $this->createClient(permissions: ['workflow_instance:access', 'workflow_task:decide', 'workflow_instance:start']);
        $stranger->jsonRequest('POST', '/api/_action/workflow/task/' . $taskId . '/approve', []);
        static::assertSame(404, $stranger->getResponse()->getStatusCode());
        $stranger->jsonRequest('POST', '/api/_action/workflow/task/' . $taskId . '/reject', ['comment' => 'Not mine']);
        static::assertSame(404, $stranger->getResponse()->getStatusCode());
        $stranger->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(403, $stranger->getResponse()->getStatusCode());
        $stranger->jsonRequest('PATCH', '/api/workflow-task/' . $taskId, ['assigneeId' => $this->actorId()]);
        static::assertSame(403, $stranger->getResponse()->getStatusCode(), $this->errorSummary($stranger));
        $stranger->jsonRequest('POST', '/api/search/workflow-task', ['ids' => [$taskId]]);
        static::assertSame(403, $stranger->getResponse()->getStatusCode());
        $stranger->jsonRequest('PATCH', '/api/workflow-instance/' . $instanceId, ['initiatorId' => $this->actorId()]);
        static::assertSame(403, $stranger->getResponse()->getStatusCode());
        $stranger->request('GET', '/api/_action/workflow/task/todo');
        static::assertSame([], $this->response($stranger)['data']);
        $owner->jsonRequest('POST', '/api/_action/workflow/task/' . $taskId . '/reject', ['comment' => 'Insufficient information']);
        static::assertSame(204, $owner->getResponse()->getStatusCode(), $this->errorSummary($owner));
        $owner->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame('REJECT', $this->response($owner)['data']['status']);
    }

    public function testSnapshotBoundDefinitionCannotBeUpdatedOrDeletedEvenByAdministrator(): void
    {
        $browser = $this->getBrowser();
        $releaseId = $this->publish($browser, $this->actorId());
        $browser->jsonRequest('PATCH', '/api/workflow-execution-definition/' . $releaseId, ['name' => 'Tampered', 'ext' => []]);
        static::assertSame(422, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $browser->request('DELETE', '/api/workflow-execution-definition/' . $releaseId);
        static::assertSame(422, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $definition = static::getContainer()->get('workflow_execution_definition.repository')->search(new Criteria([$releaseId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(WorkflowExecutionDefinitionEntity::class, $definition);
        static::assertSame('Expense approval', $definition->name);
    }

    public function testDisabledApproverBlocksPublicationAndIsRecheckedAtStart(): void
    {
        $browser = $this->getBrowser(permissions: $this->permissions());
        $actorId = $this->actorId();
        $releaseId = $this->publish($browser, $actorId);
        $other = $this->createClient(permissions: [...$this->permissions(), 'workflow:update']);
        static::getContainer()->get('user.repository')->update([['id' => $actorId, 'active' => false]], Context::createDefaultContext());
        $other->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $releaseId, 'title' => 'Cannot start', 'formData' => []]);
        static::assertSame(400, $other->getResponse()->getStatusCode());
        static::assertSame(WorkflowExecutionException::NO_ASSIGNEE, $this->response($other)['errors'][0]['code']);
        static::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM workflow_instance WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]));
        $draftId = $this->draft($other, $actorId);
        $other->jsonRequest('POST', '/api/_action/workflow/draft/' . $draftId . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(422, $other->getResponse()->getStatusCode(), $this->errorSummary($other));
        static::assertStringContainsString('approval_assignee_unavailable', $this->errorSummary($other));
    }

    /**
     * @return list<string>
     */
    private function permissions(): array
    {
        return ['workflow:read', 'workflow:create', 'workflow:publish', 'workflow_instance:access', 'workflow_instance:start', 'workflow_task:decide', 'user:read', 'state_machine_state:read'];
    }

    private function assertResponseSchema(TestBrowser $browser, string $path, string $method = 'get', string $status = '200'): void
    {
        static::assertSame((int) $status, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));
        $schemaFile = (string) file_get_contents(\dirname(__DIR__, 7) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/workflow-execution.json');
        $api = json_decode($schemaFile, true, flags: \JSON_THROW_ON_ERROR);
        $schema = $api['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'];
        $schema['components'] = $api['components'];
        $schema = json_decode(json_encode($schema, \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $schema);
        $body = json_decode((string) $browser->getResponse()->getContent(), flags: \JSON_THROW_ON_ERROR);

        static::assertTrue((new Validator())->validate($body, $schema)->isValid(), $path . ' response must conform to its OpenAPI schema.');
    }

    private function errorSummary(TestBrowser $browser): string
    {
        $response = json_decode((string) $browser->getResponse()->getContent(), true);

        return json_encode(array_map(static fn (array $error): array => array_intersect_key($error, array_flip(['status', 'code', 'detail'])), $response['errors'] ?? []), \JSON_THROW_ON_ERROR);
    }

    private function actorId(): string
    {
        return Uuid::fromBytesToHex(static::getContainer()->get(Connection::class)->fetchOne('SELECT id FROM user WHERE username = :username', ['username' => $this->apiUsernames[array_key_last($this->apiUsernames) ?? 0]]));
    }

    private function publish(TestBrowser $browser, string $assigneeId): string
    {
        $draftId = $this->draft($browser, $assigneeId);
        $browser->jsonRequest('POST', '/api/_action/workflow/draft/' . $draftId . '/publish', ['expectedRevision' => 1, 'publisher' => 'core:approval']);
        static::assertSame(201, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));

        return $this->response($browser)['id'];
    }

    private function draft(TestBrowser $browser, string $assigneeId): string
    {
        $id = Uuid::randomHex();
        $browser->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $id, 'name' => 'Expense approval', 'document' => [
            'schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [
                ['id' => 'start', 'type' => 'core:start', 'typeVersion' => 1, 'config' => ['inputType' => 'core:approval-request']],
                ['id' => 'approval', 'type' => 'core:approval', 'typeVersion' => 1, 'config' => [
                    'assignee' => ['source' => 'core:user', 'config' => ['userId' => $assigneeId]],
                    'approval' => ['mode' => 'core:single', 'config' => []],
                ]],
                ['id' => 'end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
                ['id' => 'rejected-end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
            ], 'connections' => [
                ['id' => 'start-approval', 'source' => 'start', 'sourcePort' => 'out', 'target' => 'approval', 'targetPort' => 'in'],
                ['id' => 'approved', 'source' => 'approval', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'],
                ['id' => 'rejected', 'source' => 'approval', 'sourcePort' => 'rejected', 'target' => 'rejected-end', 'targetPort' => 'in'],
            ],
        ]]);
        static::assertSame(201, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));

        return $id;
    }

    private function start(TestBrowser $browser, string $releaseId): string
    {
        $browser->jsonRequest('POST', '/api/_action/workflow/instance', ['definitionId' => $releaseId, 'title' => 'Travel expense', 'formData' => ['reason' => 'Customer visit']]);
        static::assertSame(201, $browser->getResponse()->getStatusCode(), $this->errorSummary($browser));

        $this->assertResponseSchema($browser, '/_action/workflow/instance', 'post', '201');

        return $this->response($browser)['data']['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function response(TestBrowser $browser): array
    {
        return json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
