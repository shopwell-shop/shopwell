<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimePreviewEvent;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowCopyApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testCopyRecipientCanPreviewReadAndAcknowledgeWithoutApprovalPermissions(): void
    {
        $manager = $this->getBrowser(permissions: $this->permissions());
        $managerId = $this->actorId();
        $recipient = $this->createClient(permissions: ['workflow_instance:access']);
        $recipientId = $this->actorId();
        static::getContainer()->get('user.repository')->update([['id' => $recipientId, 'email' => $recipientId . '@example.com']], Context::createDefaultContext());
        $draftId = $this->draft($manager, $managerId, copyRecipientId: $recipientId);
        $manager->jsonRequest('POST', '/api/_action/workflow/draft/' . $draftId . '/publish', [
            'expectedRevision' => 1, 'publisher' => 'core:approval',
        ]);
        static::assertSame(201, $manager->getResponse()->getStatusCode(), $this->errorSummary($manager));
        $releaseId = $this->response($manager)['id'];
        $context = Context::createDefaultContext(new AdminApiSource($managerId));
        $release = static::getContainer()->get('workflow_release.repository')->search(new Criteria([$releaseId]), $context)->first();
        static::assertInstanceOf(WorkflowReleaseEntity::class, $release);
        $preview = new WorkflowRuntimePreviewEvent($release, ['reason' => 'Customer visit'], $context);
        static::getContainer()->get('event_dispatcher')->dispatch($preview);
        $steps = $preview->getSteps();
        static::assertNotNull($steps);
        static::assertSame('copy', $steps[0]['type']);
        static::assertSame($recipientId, $steps[0]['assignees'][0]['id']);

        $instanceId = $this->start($manager, $releaseId);
        $recipient->request('GET', '/api/_action/workflow/copy?limit=1');
        $this->assertResponseSchema($recipient, '/_action/workflow/copy');
        $receipts = $this->response($recipient);
        static::assertSame(1, $receipts['meta']['total']);
        $receipt = $receipts['data'][0];
        static::assertSame($instanceId, $receipt['instanceId']);
        static::assertSame('Travel expense', $receipt['title']);
        static::assertSame('Copy recipients', $receipt['nodeName']);
        static::assertNull($receipt['readAt']);

        $recipient->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(200, $recipient->getResponse()->getStatusCode(), $this->errorSummary($recipient));
        static::assertSame('RUNNING', $this->response($recipient)['data']['status']);
        $recipient->jsonRequest('POST', '/api/_action/workflow/copy/' . $receipt['id'] . '/read');
        static::assertSame(204, $recipient->getResponse()->getStatusCode(), $this->errorSummary($recipient));
        $recipient->request('GET', '/api/_action/workflow/copy');
        $readAt = $this->response($recipient)['data'][0]['readAt'];
        static::assertNotNull($readAt);
        $recipient->jsonRequest('POST', '/api/_action/workflow/copy/' . $receipt['id'] . '/read');
        static::assertSame(204, $recipient->getResponse()->getStatusCode());
        $recipient->request('GET', '/api/_action/workflow/copy');
        static::assertSame($readAt, $this->response($recipient)['data'][0]['readAt']);

        $stranger = $this->createClient(permissions: ['workflow_instance:access']);
        $stranger->request('GET', '/api/_action/workflow/copy?userId=' . $recipientId);
        static::assertSame([], $this->response($stranger)['data']);
        $stranger->jsonRequest('POST', '/api/_action/workflow/copy/' . $receipt['id'] . '/read');
        static::assertSame(403, $stranger->getResponse()->getStatusCode(), $this->errorSummary($stranger));
        $stranger->request('GET', '/api/_action/workflow/instance/' . $instanceId);
        static::assertSame(403, $stranger->getResponse()->getStatusCode());
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
        $schemaFile = (string) file_get_contents(\dirname(__DIR__, 6) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/workflow-execution.json');
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

    private function draft(TestBrowser $browser, string $assigneeId, string $copyRecipientId): string
    {
        $id = Uuid::randomHex();
        $document = [
            'schemaVersion' => 1, 'policy' => 'core:structured', 'nodes' => [
                ['id' => 'start', 'type' => 'core:start', 'typeVersion' => 1, 'config' => ['inputType' => 'core:approval-request']],
                ['id' => 'copy', 'type' => 'core:copy', 'typeVersion' => 1, 'config' => [
                    'label' => 'Copy recipients', 'userIds' => [$copyRecipientId],
                ]],
                ['id' => 'approval', 'type' => 'core:approval', 'typeVersion' => 1, 'config' => [
                    'assignee' => ['source' => 'core:user', 'config' => ['userId' => $assigneeId]],
                    'approval' => ['mode' => 'core:single', 'config' => []],
                ]],
                ['id' => 'end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
                ['id' => 'rejected-end', 'type' => 'core:end', 'typeVersion' => 1, 'config' => []],
            ], 'connections' => [
                ['id' => 'start-copy', 'source' => 'start', 'sourcePort' => 'out', 'target' => 'copy', 'targetPort' => 'in'],
                ['id' => 'copy-approval', 'source' => 'copy', 'sourcePort' => 'out', 'target' => 'approval', 'targetPort' => 'in'],
                ['id' => 'approved', 'source' => 'approval', 'sourcePort' => 'approved', 'target' => 'end', 'targetPort' => 'in'],
                ['id' => 'rejected', 'source' => 'approval', 'sourcePort' => 'rejected', 'target' => 'rejected-end', 'targetPort' => 'in'],
            ],
        ];
        $browser->jsonRequest('POST', '/api/_action/workflow/draft', ['id' => $id, 'name' => 'Expense approval', 'document' => $document]);
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
