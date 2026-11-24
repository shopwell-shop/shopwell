<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api;

use Doctrine\DBAL\Connection;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalRequestControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-query-api-' . Uuid::randomHex();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testListAndDetailRequireThePersonalAccessPrivilege(): void
    {
        $browser = $this->getBrowser(permissions: []);
        foreach (['', '/' . Uuid::randomHex()] as $suffix) {
            $browser->request('GET', '/api/_action/approval-flow/requests' . $suffix);
            static::assertSame(403, $browser->getResponse()->getStatusCode());
        }
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $browser = $this->getBrowser(authorized: false);
        $browser->request('GET', '/api/_action/approval-flow/requests');

        static::assertSame(401, $browser->getResponse()->getStatusCode());
    }

    public function testMalformedRequestIdentifiersAreRejected(): void
    {
        $browser = $this->getBrowser(permissions: ['approval_flow:access']);
        $browser->request('GET', '/api/_action/approval-flow/requests/not-an-id');

        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    public function testInitiatorsSeeTheirOwnSubmittedForm(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $author = TestUser::createNewTestUser($connection, ['approval_flow:access']);
        $reviewer = TestUser::createNewTestUser($connection)->getUserId();
        $request = $this->createRequest($reviewer, $author->getUserId());
        $browser = $this->getBrowser(authorized: false);
        $author->authorizeBrowser($browser);
        $browser->request('GET', '/api/_action/approval-flow/requests?view=started');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $body = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(1, $body['meta']['total']);
        static::assertSame($request->getId(), $body['data'][0]['id']);

        $browser->request('GET', '/api/_action/approval-flow/requests/' . $request->getId());
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $content = (string) $browser->getResponse()->getContent();
        $body = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('Li ••••1234', $body['form']['recipient']);
        static::assertSame('CNY', $body['form']['currency']);
        $this->assertResponseSchema('/_action/approval-flow/requests/{requestId}', $content);
    }

    public function testReviewerReceivesSafeListsAndFieldFilteredDetailMatchingTheSchema(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $reviewer = TestUser::createNewTestUser($connection, ['approval_flow:access']);
        $request = $this->createRequest($reviewer->getUserId(), Uuid::randomHex());
        $browser = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($browser);

        $browser->request('GET', '/api/_action/approval-flow/requests?view=todo&limit=1&offset=0&status=running');

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $content = (string) $browser->getResponse()->getContent();
        $body = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(1, $body['meta']['total']);
        static::assertSame($request->getId(), $body['data'][0]['id']);
        static::assertArrayNotHasKey('form', $body['data'][0]);
        $this->assertResponseSchema('/_action/approval-flow/requests', $content);

        $browser->request('GET', '/api/_action/approval-flow/requests/' . $request->getId());

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $content = (string) $browser->getResponse()->getContent();
        $detail = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['amount' => 100], $detail['form']);
        static::assertSame([], $detail['decisions']);
        $this->assertResponseSchema('/_action/approval-flow/requests/{requestId}', $content);

        // Personal access never grants the generic DAL read privileges for the unfiltered records.
        $browser->request('GET', '/api/approval-flow-request/' . $request->getId());
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testCompletedDetailAndDoneListUsePersonalDecisionHistory(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $reviewer = TestUser::createNewTestUser($connection, ['approval_flow:access']);
        $request = $this->createRequest($reviewer->getUserId(), Uuid::randomHex());
        $task = $connection->fetchAssociative('SELECT id, execution_id FROM approval_flow_task WHERE assignee_id = :id', ['id' => Uuid::fromHexToBytes($reviewer->getUserId())]);
        static::assertNotFalse($task);
        $source = new AdminApiSource($reviewer->getUserId());
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $instances = $this->runtime->get(ApprovalInstanceService::class);
        $instances->decide($request->getInstanceId(), Uuid::fromBytesToHex($task['id']), Uuid::randomHex(), 2, ApprovalOutcome::Approved, 'Approved for transfer.', Context::createDefaultContext($source));
        $instances->progress($request->getInstanceId(), Uuid::fromBytesToHex($task['execution_id']), Uuid::randomHex(), 3, Context::createDefaultContext());
        $browser = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($browser);

        $browser->request('GET', '/api/_action/approval-flow/requests?view=done&status=completed');

        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $body = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(1, $body['meta']['total']);
        static::assertSame('approved', $body['data'][0]['outcome']);
        $browser->request('GET', '/api/_action/approval-flow/requests/' . $request->getId());
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $content = (string) $browser->getResponse()->getContent();
        $body = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('Approved for transfer.', $body['decisions'][0]['reason']);
        $this->assertResponseSchema('/_action/approval-flow/requests/{requestId}', $content);
    }

    public function testUnrelatedAndUnknownRequestsHaveTheSameNotFoundResponse(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $reviewer = TestUser::createNewTestUser($connection)->getUserId();
        $request = $this->createRequest($reviewer, Uuid::randomHex());
        $browser = $this->getBrowser(permissions: ['approval_flow:access']);
        foreach ([$request->getId(), Uuid::randomHex()] as $id) {
            $browser->request('GET', '/api/_action/approval-flow/requests/' . $id);
            static::assertSame(404, $browser->getResponse()->getStatusCode());
        }
        $browser->request('GET', '/api/_action/approval-flow/requests?view=started');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertSame(['data' => [], 'meta' => ['total' => 0]], json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    #[DataProvider('invalidQueries')]
    public function testMalformedQueryParametersAreRejected(string $query): void
    {
        $browser = $this->getBrowser(permissions: ['approval_flow:access']);
        $browser->request('GET', '/api/_action/approval-flow/requests?' . $query);

        static::assertSame(400, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public static function invalidQueries(): iterable
    {
        yield 'invalid view' => ['view=everyone'];
        yield 'invalid status' => ['status=approved'];
        yield 'zero limit' => ['limit=0'];
        yield 'oversized page' => ['limit=101'];
        yield 'negative offset' => ['offset=-1'];
        yield 'fractional page size' => ['limit=1.5'];
        yield 'array view' => ['view[]=todo'];
        yield 'array limit' => ['limit[]=1'];
    }

    private function createRequest(string $reviewerId, string $authorId): ApprovalRequestEntity
    {
        $kernel = static::getContainer();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $graph = json_decode(str_replace('finance-user', $reviewerId, GraphFixture::json()), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph);
        $nodes = $graph->nodes;
        static::assertIsArray($nodes);
        $node = $nodes[1];
        static::assertInstanceOf(\stdClass::class, $node);
        $configuration = $node->config;
        static::assertInstanceOf(\stdClass::class, $configuration);
        $configuration->fields = [(object) ['field' => 'form.amount', 'access' => 'readOnly', 'required' => false]];
        $releaseId = ApprovalRuntimeFixture::publish($kernel, $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $source = new AdminApiSource($authorId);
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $context = Context::createDefaultContext($source);
        $requests = $this->runtime->get(ApprovalRequestService::class);
        $request = $requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), '{"amount":100,"currency":"CNY","recipient":"Li ••••1234","paymentAccount":"Merchant","reason":null}', $context);
        $requests->start($request->getId(), $context);

        return $request;
    }

    private function assertResponseSchema(string $path, string $content): void
    {
        $schemaPath = \dirname(__DIR__, 6) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/approval-flow-query.json';
        $definition = json_decode($this->filesystem->readFile($schemaPath), true, flags: \JSON_THROW_ON_ERROR);
        $responseSchema = $definition['paths'][$path]['get']['responses']['200']['content']['application/json']['schema'];
        $schema = json_decode(json_encode(array_merge($responseSchema, ['components' => $definition['components']]), \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $schema);

        static::assertTrue((new Validator())->validate(json_decode($content, flags: \JSON_THROW_ON_ERROR), $schema)->isValid(), $content);
    }
}
