<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Query;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalRequestQuery;
use Shopwell\Core\Framework\ApprovalFlow\Query\ApprovalRequestView;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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
class ApprovalRequestQueryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private ContainerBuilder $runtime;

    private Filesystem $filesystem;

    private string $directory;

    private string $authorId;

    private string $reviewerId;

    private string $otherReviewerId;

    private ApprovalRequestEntity $request;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->authorId = TestUser::createNewTestUser($this->connection)->getUserId();
        $this->reviewerId = TestUser::createNewTestUser($this->connection)->getUserId();
        $this->otherReviewerId = TestUser::createNewTestUser($this->connection)->getUserId();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-query-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $configuration = $this->assignReviewers($graph);
        $policy = $configuration->policy;
        static::assertInstanceOf(\stdClass::class, $policy);
        $policy->kind = 'core:any';
        $configuration->fields = [
            ['field' => 'form.amount', 'access' => 'readOnly', 'required' => false],
            ['field' => 'form.recipient', 'access' => 'hidden', 'required' => false],
        ];
        $releaseId = ApprovalRuntimeFixture::publish($kernel, $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $this->request = $this->submit($this->authorId);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testTodoBelongsToTheCurrentReviewerAndReturnsNoForm(): void
    {
        $query = static::getContainer()->get(ApprovalRequestQuery::class);
        $result = $query->search(ApprovalRequestView::Todo, limit: 25, offset: 0, status: null, context: $this->context($this->reviewerId));

        static::assertSame(1, $result['meta']['total']);
        static::assertSame($this->request->getId(), $result['data'][0]['id']);
        static::assertSame('running', $result['data'][0]['status']);
        static::assertArrayNotHasKey('form', $result['data'][0]);
        static::assertSame(0, $query->search(ApprovalRequestView::Todo, 25, 0, null, $this->context($this->authorId))['meta']['total']);
    }

    public function testDoneExcludesTasksCancelledByAnotherReviewersDecision(): void
    {
        $this->decide(ApprovalOutcome::Approved, 'Agreed with the amount.');
        $query = static::getContainer()->get(ApprovalRequestQuery::class);

        static::assertSame(1, $query->search(ApprovalRequestView::Done, 25, 0, null, $this->context($this->reviewerId))['meta']['total']);
        static::assertSame(0, $query->search(ApprovalRequestView::Done, 25, 0, null, $this->context($this->otherReviewerId))['meta']['total']);
        static::assertSame(0, $query->search(ApprovalRequestView::Todo, 25, 0, null, $this->context($this->otherReviewerId))['meta']['total']);
    }

    public function testStartedAppliesOwnershipBeforePagingAndCounting(): void
    {
        $second = $this->submit($this->authorId);
        $this->submit($this->otherReviewerId);
        $query = static::getContainer()->get(ApprovalRequestQuery::class);
        $first = $query->search(ApprovalRequestView::Started, 1, 0, null, $this->context($this->authorId));
        $next = $query->search(ApprovalRequestView::Started, 1, 1, null, $this->context($this->authorId));

        static::assertSame(2, $first['meta']['total']);
        static::assertSame(2, $next['meta']['total']);
        static::assertCount(1, $first['data']);
        static::assertCount(1, $next['data']);
        static::assertEqualsCanonicalizing([$this->request->getId(), $second->getId()], [$first['data'][0]['id'], $next['data'][0]['id']]);
        static::assertSame([], $query->search(ApprovalRequestView::Started, 25, 2, null, $this->context($this->authorId))['data']);
    }

    public function testTodoExcludesSuspendedRequestsButStartedCanFilterThem(): void
    {
        $source = new AdminApiSource($this->authorId);
        $source->setPermissions(['approval_flow_instance:manage']);
        $this->runtime->get(ApprovalInstanceService::class)->suspend($this->request->getInstanceId(), Uuid::randomHex(), 2, 'Review paused', Context::createDefaultContext($source));
        $query = static::getContainer()->get(ApprovalRequestQuery::class);

        static::assertSame(0, $query->search(ApprovalRequestView::Todo, 25, 0, null, $this->context($this->reviewerId))['meta']['total']);
        static::assertSame(1, $query->search(ApprovalRequestView::Started, 25, 0, ApprovalInstanceStatus::Suspended, $this->context($this->authorId))['meta']['total']);
        static::assertSame(0, $query->search(ApprovalRequestView::Started, 25, 0, ApprovalInstanceStatus::Running, $this->context($this->authorId))['meta']['total']);
    }

    public function testReviewerDetailHidesUnassignedFieldsAndRetainsPersonalOpinions(): void
    {
        $this->decide(ApprovalOutcome::Rejected, 'The payment needs correction.');
        $query = static::getContainer()->get(ApprovalRequestQuery::class);

        $detail = $query->detail($this->request->getId(), $this->context($this->reviewerId));

        static::assertEquals((object) ['amount' => 100], $detail['form']);
        static::assertCount(2, $detail['tasks']);
        static::assertCount(1, $detail['decisions']);
        static::assertSame('rejected', $detail['decisions'][0]['decision']);
        static::assertSame($this->reviewerId, $detail['decisions'][0]['assigneeId']);
        static::assertSame('The payment needs correction.', $detail['decisions'][0]['reason']);
        static::assertEquals((object) ['amount' => 100], $query->detail($this->request->getId(), $this->context($this->otherReviewerId))['form']);
        static::assertSame('Li ••••1234', $query->detail($this->request->getId(), $this->context($this->authorId))['form']->recipient);
        static::assertArrayNotHasKey('actorJson', $detail['decisions'][0]);
        static::assertArrayNotHasKey('businessRevision', $detail);
    }

    public function testOutsidersCannotReadARequestEvenWithItsIdentifier(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::requestNotFound($this->request->getId()));

        static::getContainer()->get(ApprovalRequestQuery::class)->detail($this->request->getId(), $this->context(Uuid::randomHex()));
    }

    public function testPersonalQueriesRequireAnAdministratorUserIdentity(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::requestActorRequired());

        static::getContainer()->get(ApprovalRequestQuery::class)->search(ApprovalRequestView::Started, 25, 0, null, Context::createDefaultContext());
    }

    public function testIntegrationIdentityCannotReadPersonalRequests(): void
    {
        $source = new AdminApiSource($this->authorId, Uuid::randomHex());
        $this->expectExceptionObject(ApprovalFlowException::requestActorRequired());

        static::getContainer()->get(ApprovalRequestQuery::class)->detail($this->request->getId(), Context::createDefaultContext($source));
    }

    public function testOtherPendingTasksDoNotKeepMyDecidedTaskInTodo(): void
    {
        $this->decide(ApprovalOutcome::Approved, 'First request finished.');
        $request = $this->submitAllReviewersRequest();
        $taskId = $this->connection->fetchOne('SELECT t.id FROM approval_flow_task t INNER JOIN approval_flow_execution e ON e.id = t.execution_id WHERE e.instance_id = :instance AND t.assignee_id = :user', ['instance' => Uuid::fromHexToBytes($request->getInstanceId()), 'user' => Uuid::fromHexToBytes($this->reviewerId)]);
        static::assertIsString($taskId);
        $this->runtime->get(ApprovalInstanceService::class)->decide($request->getInstanceId(), Uuid::fromBytesToHex($taskId), Uuid::randomHex(), 2, ApprovalOutcome::Approved, 'My review is finished.', $this->context($this->reviewerId));
        $query = static::getContainer()->get(ApprovalRequestQuery::class);

        static::assertSame(0, $query->search(ApprovalRequestView::Todo, 25, 0, null, $this->context($this->reviewerId))['meta']['total']);
        static::assertSame(1, $query->search(ApprovalRequestView::Todo, 25, 0, null, $this->context($this->otherReviewerId))['meta']['total']);
        static::assertSame(2, $query->search(ApprovalRequestView::Done, 25, 0, null, $this->context($this->reviewerId))['meta']['total']);
    }

    public function testOpinionsFollowDecisionOrderInsteadOfTaskIdentifierOrder(): void
    {
        $request = $this->submitAllReviewersRequest();
        $tasks = $this->connection->fetchAllAssociative('SELECT t.id, t.assignee_id FROM approval_flow_task t INNER JOIN approval_flow_execution e ON e.id = t.execution_id WHERE e.instance_id = :instance ORDER BY t.id', ['instance' => Uuid::fromHexToBytes($request->getInstanceId())]);
        static::assertCount(2, $tasks);
        $instances = $this->runtime->get(ApprovalInstanceService::class);
        // The larger task ID votes first; the detail must retain the order in which decisions committed.
        $instances->decide($request->getInstanceId(), Uuid::fromBytesToHex($tasks[1]['id']), Uuid::randomHex(), 2, ApprovalOutcome::Approved, 'Earlier opinion', $this->context(Uuid::fromBytesToHex($tasks[1]['assignee_id'])));
        $instances->decide($request->getInstanceId(), Uuid::fromBytesToHex($tasks[0]['id']), Uuid::randomHex(), 3, ApprovalOutcome::Approved, 'Later opinion', $this->context(Uuid::fromBytesToHex($tasks[0]['assignee_id'])));

        $detail = static::getContainer()->get(ApprovalRequestQuery::class)->detail($request->getId(), $this->context($this->authorId));

        static::assertSame(['Earlier opinion', 'Later opinion'], array_column($detail['decisions'], 'reason'));
        static::assertSame([3, 4], array_column($detail['decisions'], 'aggregateVersion'));
    }

    private function submitAllReviewersRequest(): ApprovalRequestEntity
    {
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        $this->assignReviewers($graph);
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 1, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 2, 1, Context::createDefaultContext());

        return $this->submit($this->authorId);
    }

    private function decide(ApprovalOutcome $outcome, string $reason): void
    {
        $taskId = $this->connection->fetchOne('SELECT id FROM approval_flow_task WHERE assignee_id = :id', ['id' => Uuid::fromHexToBytes($this->reviewerId)]);
        static::assertIsString($taskId);
        $this->runtime->get(ApprovalInstanceService::class)->decide($this->request->getInstanceId(), Uuid::fromBytesToHex($taskId), Uuid::randomHex(), 2, $outcome, $reason, $this->context($this->reviewerId));
    }

    private function assignReviewers(mixed $graph): \stdClass
    {
        static::assertInstanceOf(\stdClass::class, $graph);
        $nodes = $graph->nodes;
        static::assertIsArray($nodes);
        $node = $nodes[1];
        static::assertInstanceOf(\stdClass::class, $node);
        $configuration = $node->config;
        static::assertInstanceOf(\stdClass::class, $configuration);
        $assignees = $configuration->assignees;
        static::assertIsArray($assignees);
        $users = $assignees[0];
        static::assertInstanceOf(\stdClass::class, $users);
        $users->userIds = [$this->reviewerId, $this->otherReviewerId];

        return $configuration;
    }

    private function submit(string $authorId): ApprovalRequestEntity
    {
        $service = $this->runtime->get(ApprovalRequestService::class);
        $context = $this->context($authorId);
        $request = $service->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), '{"amount":100,"currency":"CNY","recipient":"Li ••••1234","paymentAccount":"Merchant","reason":null}', $context);
        $service->start($request->getId(), $context);

        return $request;
    }

    private function context(string $userId): Context
    {
        $source = new AdminApiSource($userId);
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create', 'approval_flow_task:decide']);

        return Context::createDefaultContext($source);
    }
}
