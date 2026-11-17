<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalReminderApiTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @var list<string>
     */
    private array $definitionIds = [];

    protected function tearDown(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        foreach ($this->definitionIds as $id) {
            $definitionId = Uuid::fromHexToBytes($id);
            $connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_task task ON task.id = history.referenced_id INNER JOIN workflow_instance instance ON instance.id = task.instance_id WHERE instance.definition_id = :id', ['id' => $definitionId]);
            $connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_instance instance ON instance.id = history.referenced_id WHERE instance.definition_id = :id', ['id' => $definitionId]);
            $connection->delete('workflow_instance', ['definition_id' => $definitionId]);
            $connection->delete('workflow_execution_definition', ['id' => $definitionId]);
        }
        $roles = $connection->fetchFirstColumn('SELECT role.acl_role_id FROM acl_user_role role INNER JOIN user ON user.id = role.user_id WHERE user.username IN (:names)', ['names' => $this->apiUsernames], ['names' => ArrayParameterType::STRING]);
        $connection->executeStatement('DELETE FROM user WHERE username IN (:names)', ['names' => $this->apiUsernames], ['names' => ArrayParameterType::STRING]);
        if ($roles !== []) {
            $connection->executeStatement('DELETE FROM acl_role WHERE id IN (:ids)', ['ids' => $roles], ['ids' => ArrayParameterType::BINARY]);
        }
    }

    public function testInitiatorMayRemindButApproversAndStrangersCannot(): void
    {
        $initiator = $this->createClient(permissions: ['workflow_instance:access', 'workflow_instance:start']);
        $actorId = $this->actorId();
        $approver = $this->createClient(permissions: ['workflow_instance:access', 'workflow_task:decide']);
        $approverId = $this->actorId();
        $context = Context::createDefaultContext();
        static::getContainer()->get('user.repository')->update([['id' => $approverId, 'email' => $approverId . '@example.invalid']], $context);
        $workflows = static::getContainer()->get(WorkflowService::class);
        $definition = $workflows->createDefinition([
            'code' => Uuid::randomHex(), 'name' => 'Reminder API', 'graphData' => [
                'nodes' => [
                    ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => []]],
                    ['id' => 'review', 'type' => 'snaker:task', 'properties' => ['name' => 'Review', 'approvalStage' => true, 'assigneeType' => 'user', 'assigneeValue' => [$approverId], 'countersignType' => 'single']],
                    ['id' => 'end', 'type' => 'snaker:end'],
                ],
                'edges' => [
                    ['id' => 'start-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
                    ['id' => 'review-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
                ],
            ],
        ], $context);
        $this->definitionIds[] = $definition;
        $workflows->publishDefinition($definition, $context);
        $instanceId = $workflows->start($definition, [], 'Reminder API', Context::createDefaultContext(new AdminApiSource($actorId)));
        $endpoint = '/api/_action/workflow/instance/' . $instanceId . '/remind';
        $approver->jsonRequest('POST', $endpoint);
        static::assertSame(403, $approver->getResponse()->getStatusCode());
        $stranger = $this->createClient(permissions: ['workflow_instance:start']);
        $stranger->jsonRequest('POST', $endpoint);
        static::assertSame(403, $stranger->getResponse()->getStatusCode());
        $initiator->jsonRequest('POST', $endpoint);
        static::assertSame(204, $initiator->getResponse()->getStatusCode(), (string) $initiator->getResponse()->getContent());
        $initiator->jsonRequest('POST', $endpoint);
        static::assertSame(409, $initiator->getResponse()->getStatusCode());
        $initiator->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $initiator->getResponse()->getStatusCode());
        $detail = json_decode((string) $initiator->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
        static::assertFalse($detail['canRemind']);
        static::assertSame(1, $detail['tasks'][0]['reminder']['count']);
        static::assertNull($detail['tasks'][0]['reminder']['dueAt']);
        $approver->request('GET', '/api/_action/workflow/task/todo');
        $tasks = json_decode((string) $approver->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR)['data'];
        static::assertSame(1, $tasks[0]['reminder']['count']);
        static::assertFalse($tasks[0]['reminder']['canRemind']);
    }

    public function testGenericApiCannotEnumeratePrivateReminderMetadata(): void
    {
        $administrator = $this->createClient();
        foreach (['/api/workflow-approval-reminder', '/api/workflow-approval-reminder/' . Uuid::randomHex(), '/api/search/workflow-approval-reminder', '/api/search-ids/workflow-approval-reminder', '/api/aggregate/workflow-approval-reminder'] as $endpoint) {
            $method = str_starts_with($endpoint, '/api/workflow-approval-reminder') ? 'GET' : 'POST';
            $administrator->jsonRequest($method, $endpoint);
            static::assertSame(403, $administrator->getResponse()->getStatusCode(), $endpoint);
        }
    }

    private function actorId(): string
    {
        $lastIndex = array_key_last($this->apiUsernames);
        static::assertNotNull($lastIndex);

        return Uuid::fromBytesToHex(static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT id FROM user WHERE username = :username',
            ['username' => $this->apiUsernames[$lastIndex]],
        ));
    }
}
