<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Api;

use Doctrine\DBAL\Connection;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalTaskControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use EnvTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-decision-api-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $this->setEnvVars(['APPROVAL_FLOW_ARTIFACT_MANIFEST' => $manifest]);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    #[DataProvider('outcomes')]
    public function testTheAssigneeCommitsOneOpinionAndCanReplayItAfterProgression(string $outcome): void
    {
        $reviewer = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), [ApprovalInstanceService::DECIDE_PRIVILEGE, 'approval_flow:access']);
        [$request, $taskId, $executionId] = $this->createRequest($reviewer->getUserId());
        $browser = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($browser);
        $path = $this->path($request->getInstanceId(), $taskId);
        $payload = ['commandId' => Uuid::randomHex(), 'expectedVersion' => 2, 'outcome' => $outcome, 'reason' => 'Reviewed the transfer.'];

        $browser->jsonRequest('POST', $path, $payload);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $receipt = (string) $browser->getResponse()->getContent();
        static::assertSame([
            'instanceId' => $request->getInstanceId(), 'commandId' => $payload['commandId'],
            'status' => 'running', 'aggregateVersion' => 3, 'outcome' => null,
        ], json_decode($receipt, true, flags: \JSON_THROW_ON_ERROR));
        $definition = json_decode($this->filesystem->readFile(\dirname(__DIR__, 6) . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/approval-flow-task.json'), true, flags: \JSON_THROW_ON_ERROR);
        $schema = json_decode(json_encode($definition['components']['schemas']['ApprovalDecisionReceipt'], \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $schema);
        static::assertTrue((new Validator())->validate(json_decode($receipt, flags: \JSON_THROW_ON_ERROR), $schema)->isValid());
        $this->runtime->get(ApprovalInstanceService::class)->progress($request->getInstanceId(), $executionId, Uuid::randomHex(), 3, Context::createDefaultContext());
        $browser->jsonRequest('POST', $path, $payload);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertSame($receipt, $browser->getResponse()->getContent());
        $browser->request('GET', '/api/_action/approval-flow/requests/' . $request->getId());
        $detail = json_decode($browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($outcome, $detail['request']['outcome']);
        static::assertCount(1, $detail['decisions']);
        static::assertSame('Reviewed the transfer.', $detail['decisions'][0]['reason']);

        $browser->jsonRequest('POST', $path, array_replace($payload, ['reason' => 'Changed opinion.']));
        $this->assertError($browser, 409, ApprovalFlowException::INSTANCE_COMMAND_CONFLICT);
    }

    public function testTheAssigneeCanReturnToAnEarlierExecutionAndReviewAgain(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $permissions = [ApprovalInstanceService::DECIDE_PRIVILEGE, 'approval_flow:access'];
        $reviewer = TestUser::createNewTestUser($connection, $permissions);
        $returner = TestUser::createNewTestUser($connection, $permissions);
        $colleague = TestUser::createNewTestUser($connection, $permissions);
        $graph = GraphFixture::codec()->decodeWritable(new DraftGraph(GraphFixture::json()));
        $previous = 'review';
        foreach (['second-review', 'final-review'] as $nodeId) {
            $node = clone $graph->nodes[1];
            $node->id = $nodeId;
            if ($nodeId === 'final-review') {
                $node->config = clone $node->config;
                $node->config->assignees = [['kind' => 'core:users', 'userIds' => [$returner->getUserId(), $colleague->getUserId()]]];
            }
            $graph->nodes[] = $node;
            $connectionToReview = clone $graph->connections[1];
            $connectionToReview->id = $previous . '-' . $nodeId;
            $connectionToReview->source = $previous;
            $connectionToReview->target = $nodeId;
            $graph->connections[] = $connectionToReview;
            $previous = $nodeId;
        }
        $graph->connections[1]->source = $previous;
        [$request, $firstTask, $firstExecution] = $this->createRequest($reviewer->getUserId(), json_encode($graph, \JSON_THROW_ON_ERROR));

        $browser = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($browser);
        $instanceId = $request->getInstanceId();
        $browser->jsonRequest('POST', $this->path($instanceId, $firstTask), $this->payload());
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $instances = $this->runtime->get(ApprovalInstanceService::class);
        $system = Context::createDefaultContext();
        $instances->progress($instanceId, $firstExecution, Uuid::randomHex(), 3, $system);
        $detailPath = '/api/_action/approval-flow/requests/' . $request->getId();
        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $second = $detail['tasks'][1];
        $browser->jsonRequest('POST', $this->path($instanceId, $second['id']), array_replace($this->payload(), ['expectedVersion' => 4]));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $instances->progress($instanceId, $second['executionId'], Uuid::randomHex(), 5, $system);

        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $tasks = array_column($detail['tasks'], 'id', 'assigneeId');
        $path = '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $tasks[$returner->getUserId()];
        $payload = ['commandId' => Uuid::randomHex(), 'expectedVersion' => 6, 'targetExecutionId' => $firstExecution, 'reason' => 'Recheck the transfer.'];
        $browser->jsonRequest('POST', $path . '/return', $payload);
        $this->assertError($browser, 403, ApprovalFlowException::TASK_ASSIGNEE_REQUIRED);

        $returner->authorizeBrowser($browser);
        $browser->request('GET', $path . '/return-targets');
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $targets = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([$second['executionId'], $firstExecution], array_column($targets['data'], 'executionId'));

        $browser->jsonRequest('POST', $path . '/return', array_replace($payload, ['expectedVersion' => 5]));
        $this->assertError($browser, 409, ApprovalFlowException::INSTANCE_VERSION_CONFLICT);
        $browser->jsonRequest('POST', $path . '/return', $payload);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $receipt = $browser->getResponse()->getContent();
        $browser->jsonRequest('POST', $path . '/return', $payload);
        static::assertSame($receipt, $browser->getResponse()->getContent());

        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame('running', $detail['request']['status']);
        static::assertNull($detail['request']['outcome']);
        static::assertSame(['approved', 'approved', 'cancelled', 'cancelled', 'pending'], array_column($detail['tasks'], 'status'));
        static::assertSame([$firstTask, $second['id']], array_column($detail['decisions'], 'taskId'));
        static::assertSame([$firstExecution], array_column($detail['returns'], 'targetExecutionId'));
        static::assertSame(['Recheck the transfer.'], array_column($detail['returns'], 'reason'));
        static::assertSame('review', $detail['executions'][4]['nodeId']);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));

        $browser->request('GET', '/api/_action/approval-flow/requests?view=done');
        $done = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([$request->getId()], array_column($done['data'], 'id'));
        $colleague->authorizeBrowser($browser);
        $browser->request('GET', '/api/_action/approval-flow/requests?view=done');
        $done = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([], $done['data']);

        $reviewer->authorizeBrowser($browser);
        $reopened = $detail['tasks'][4];
        $reopenedPath = '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $reopened['id'];
        $browser->request('GET', $reopenedPath . '/return-targets');
        $targets = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([], $targets['data']);
        $browser->jsonRequest('POST', $reopenedPath . '/return', [
            'commandId' => Uuid::randomHex(), 'expectedVersion' => 7, 'targetExecutionId' => $second['executionId'], 'reason' => 'Use an old review.',
        ]);
        $this->assertError($browser, 409, ApprovalFlowException::RETURN_TARGET_UNAVAILABLE);

        $browser->jsonRequest('POST', $this->path($instanceId, $reopened['id']), array_replace($this->payload(), ['expectedVersion' => 7]));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $instances->progress($instanceId, $reopened['executionId'], Uuid::randomHex(), 8, $system);
        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['approved', 'approved', 'cancelled', 'cancelled', 'approved', 'pending'], array_column($detail['tasks'], 'status'));
        static::assertSame('second-review', $detail['executions'][5]['nodeId']);
    }

    public function testTransferReplacesOnePendingTaskAndKeepsEarlierOpinions(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $permissions = [ApprovalInstanceService::DECIDE_PRIVILEGE, 'approval_flow:access'];
        $first = TestUser::createNewTestUser($connection, $permissions);
        $owner = TestUser::createNewTestUser($connection, $permissions);
        $other = TestUser::createNewTestUser($connection, $permissions);
        $recipient = TestUser::createNewTestUser($connection, $permissions);
        $viewer = TestUser::createNewTestUser($connection, ['approval_flow:access']);
        $graph = GraphFixture::codec()->decodeWritable(new DraftGraph(GraphFixture::json()));
        $graph->nodes[1]->config->assignees = [[
            'kind' => 'core:users',
            'userIds' => [$first->getUserId(), $owner->getUserId(), $other->getUserId()],
        ]];
        [$request, , $executionId] = $this->createRequest($first->getUserId(), json_encode($graph, \JSON_THROW_ON_ERROR));
        $instanceId = $request->getInstanceId();
        $detailPath = '/api/_action/approval-flow/requests/' . $request->getId();

        $browser = $this->getBrowser(authorized: false);
        $first->authorizeBrowser($browser);
        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $tasks = array_column($detail['tasks'], 'id', 'assigneeId');
        $browser->jsonRequest('POST', $this->path($instanceId, $tasks[$first->getUserId()]), $this->payload());
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $owner->authorizeBrowser($browser);
        $path = '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $tasks[$owner->getUserId()];
        $browser->request('GET', $path . '/transfer-candidates');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $candidates = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $candidateIds = array_column($candidates['data'], 'id');
        static::assertContains($recipient->getUserId(), $candidateIds);
        static::assertNotContains($viewer->getUserId(), $candidateIds);
        static::assertNotContains($first->getUserId(), $candidateIds);
        static::assertNotContains($other->getUserId(), $candidateIds);
        $payload = ['commandId' => Uuid::randomHex(), 'expectedVersion' => 3, 'assigneeId' => $recipient->getUserId(), 'reason' => 'Handover.'];
        $browser->jsonRequest('POST', $path . '/transfer', array_replace($payload, ['assigneeId' => $other->getUserId()]));
        static::assertSame(409, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', $path . '/transfer', $payload);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $receipt = $browser->getResponse()->getContent();
        $browser->jsonRequest('POST', $path . '/transfer', $payload);
        static::assertSame($receipt, $browser->getResponse()->getContent());

        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $transferredTasks = array_column($detail['tasks'], null, 'assigneeId');
        $newTask = $transferredTasks[$recipient->getUserId()];
        static::assertSame($executionId, $newTask['executionId']);
        static::assertSame('cancelled', $transferredTasks[$owner->getUserId()]['status']);
        static::assertSame('pending', $transferredTasks[$other->getUserId()]['status']);
        static::assertSame([$tasks[$first->getUserId()]], array_column($detail['decisions'], 'taskId'));
        static::assertSame([$newTask['id']], array_column($detail['transfers'], 'replacementTaskId'));
        static::assertNull($detail['request']['outcome']);
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));

        $browser->jsonRequest('POST', $this->path($instanceId, $tasks[$owner->getUserId()]), array_replace($this->payload(), ['expectedVersion' => 4]));
        $this->assertError($browser, 409, ApprovalFlowException::TASK_NOT_PENDING);
        $other->authorizeBrowser($browser);
        $browser->jsonRequest('POST', $this->path($instanceId, $tasks[$other->getUserId()]), array_replace($this->payload(), ['expectedVersion' => 4]));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $recipient->authorizeBrowser($browser);
        $browser->jsonRequest('POST', '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $newTask['id'] . '/transfer', [
            'commandId' => Uuid::randomHex(), 'expectedVersion' => 5, 'assigneeId' => $owner->getUserId(), 'reason' => 'Back to the original reviewer.',
        ]);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $owner->authorizeBrowser($browser);
        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame([$tasks[$owner->getUserId()], $newTask['id']], array_column($detail['transfers'], 'taskId'));
        $replacementId = $detail['transfers'][1]['replacementTaskId'];

        $browser->jsonRequest('POST', $this->path($instanceId, $replacementId), array_replace($this->payload(), ['expectedVersion' => 6]));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $receipt = $this->runtime->get(ApprovalInstanceService::class)->progress($instanceId, $executionId, Uuid::randomHex(), 7, Context::createDefaultContext());
        static::assertSame('approved', $receipt->outcome?->value);
    }

    #[DataProvider('preApprovalOutcomes')]
    public function testPreApprovalHoldsOnlyTheOriginalTaskAndHandlesTheSignersDecision(string $outcome, string $policy): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $permissions = [ApprovalInstanceService::DECIDE_PRIVILEGE, 'approval_flow:access'];
        $owner = TestUser::createNewTestUser($connection, $permissions);
        $peer = TestUser::createNewTestUser($connection, $permissions);
        $signer = TestUser::createNewTestUser($connection, $permissions);
        $graph = GraphFixture::codec()->decodeWritable(new DraftGraph(GraphFixture::json()));
        $graph->nodes[1]->config->assignees = [[
            'kind' => 'core:users',
            'userIds' => [$owner->getUserId(), $peer->getUserId()],
        ]];
        $graph->nodes[1]->config->policy = ['kind' => $policy, 'reject' => 'veto'];
        [$request, , $executionId] = $this->createRequest($owner->getUserId(), json_encode($graph, \JSON_THROW_ON_ERROR));
        $instanceId = $request->getInstanceId();
        $detailPath = '/api/_action/approval-flow/requests/' . $request->getId();

        $browser = $this->getBrowser(authorized: false);
        $owner->authorizeBrowser($browser);
        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $tasks = array_column($detail['tasks'], 'id', 'assigneeId');
        $ownerTask = $tasks[$owner->getUserId()];
        $peerTask = $tasks[$peer->getUserId()];
        $path = '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $ownerTask . '/add-sign';
        $payload = ['commandId' => Uuid::randomHex(), 'expectedVersion' => 2, 'assigneeId' => $signer->getUserId(), 'reason' => 'Verify the transfer first.'];
        $browser->jsonRequest('POST', $path, $payload);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $receipt = $browser->getResponse()->getContent();
        $browser->jsonRequest('POST', $path, $payload);
        static::assertSame($receipt, $browser->getResponse()->getContent());

        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $held = array_column($detail['tasks'], null, 'assigneeId');
        $signerTask = $held[$signer->getUserId()]['id'];
        static::assertSame('blocked', $held[$owner->getUserId()]['status']);
        static::assertSame('pending', $held[$peer->getUserId()]['status']);
        static::assertSame('pending', $held[$signer->getUserId()]['status']);
        static::assertSame($executionId, $held[$signer->getUserId()]['executionId']);
        static::assertSame([$signerTask], array_column($detail['addSigns'], 'addedTaskId'));

        $browser->request('GET', '/api/_action/approval-flow/requests?view=todo');
        $todo = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertContains($request->getId(), array_column($todo['data'], 'id'));

        $browser->jsonRequest('POST', $this->path($instanceId, $ownerTask), array_replace($this->payload(), ['expectedVersion' => 3]));
        $this->assertError($browser, 409, ApprovalFlowException::TASK_NOT_PENDING);
        $peer->authorizeBrowser($browser);
        $browser->jsonRequest('POST', $this->path($instanceId, $peerTask), array_replace($this->payload(), ['expectedVersion' => 3]));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        $signer->authorizeBrowser($browser);
        $browser->jsonRequest('POST', '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $signerTask . '/add-sign', [
            'commandId' => Uuid::randomHex(), 'expectedVersion' => 4, 'assigneeId' => $peer->getUserId(), 'reason' => 'Another review.',
        ]);
        $this->assertError($browser, 409, ApprovalFlowException::PRIMARY_TASK_REQUIRED);
        $browser->jsonRequest('POST', $this->path($instanceId, $signerTask), array_replace($this->payload(), ['expectedVersion' => 4, 'outcome' => $outcome]));
        static::assertSame(200, $browser->getResponse()->getStatusCode());

        $owner->authorizeBrowser($browser);
        $browser->request('GET', $detailPath);
        $detail = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $decided = array_column($detail['tasks'], null, 'assigneeId');
        static::assertSame([$peerTask, $signerTask], array_column($detail['decisions'], 'taskId'));
        static::assertSame($outcome, $decided[$signer->getUserId()]['status']);

        $version = 5;
        if ($outcome === 'approved') {
            static::assertSame('pending', $decided[$owner->getUserId()]['status']);
            static::assertSame('awaiting_approval', array_column($detail['executions'], 'status', 'id')[$executionId]);
            $browser->jsonRequest('POST', $this->path($instanceId, $ownerTask), array_replace($this->payload(), ['expectedVersion' => $version]));
            static::assertSame(200, $browser->getResponse()->getStatusCode());
            ++$version;
        } else {
            static::assertSame('cancelled', $decided[$owner->getUserId()]['status']);
        }

        $receipt = $this->runtime->get(ApprovalInstanceService::class)->progress($instanceId, $executionId, Uuid::randomHex(), $version, Context::createDefaultContext());
        static::assertSame($outcome, $receipt->outcome?->value);
    }

    public static function preApprovalOutcomes(): iterable
    {
        yield 'approval in a unanimous review' => ['approved', 'core:all'];
        yield 'veto in a unanimous review' => ['rejected', 'core:all'];
        yield 'one existing opinion cannot skip the additional review' => ['approved', 'core:any'];
    }

    public static function outcomes(): iterable
    {
        yield 'approved' => ['approved'];
        yield 'rejected' => ['rejected'];
    }

    public function testUsesTheHostNormalizerForANumericVersionString(): void
    {
        $reviewer = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), [ApprovalInstanceService::DECIDE_PRIVILEGE]);
        [$request, $taskId] = $this->createRequest($reviewer->getUserId());
        $browser = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($browser);
        $browser->jsonRequest('POST', $this->path($request->getInstanceId(), $taskId), array_replace($this->payload(), ['expectedVersion' => '2']));

        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testAViewerCannotMakeADecision(): void
    {
        $browser = $this->getBrowser(permissions: ['approval_flow:access']);
        $browser->jsonRequest('POST', $this->path(Uuid::randomHex(), Uuid::randomHex()), $this->payload());
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testAuthenticationIsRequired(): void
    {
        $browser = $this->getBrowser(authorized: false);
        $browser->jsonRequest('POST', $this->path(Uuid::randomHex(), Uuid::randomHex()), $this->payload());
        static::assertSame(401, $browser->getResponse()->getStatusCode());
    }

    public function testThePrivilegeCannotReplaceTaskOwnership(): void
    {
        $assigned = TestUser::createNewTestUser(static::getContainer()->get(Connection::class));
        [$request, $taskId] = $this->createRequest($assigned->getUserId());
        $browser = $this->getBrowser(permissions: [ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $browser->jsonRequest('POST', $this->path($request->getInstanceId(), $taskId), array_replace($this->payload(), ['userId' => $assigned->getUserId(), 'actor' => ['userId' => $assigned->getUserId()]]));
        $this->assertError($browser, 403, ApprovalFlowException::TASK_ASSIGNEE_REQUIRED);
    }

    public function testAnOldVersionDoesNotRecordAnOpinion(): void
    {
        $reviewer = TestUser::createNewTestUser(static::getContainer()->get(Connection::class), [ApprovalInstanceService::DECIDE_PRIVILEGE]);
        [$request, $taskId] = $this->createRequest($reviewer->getUserId());
        $browser = $this->getBrowser(authorized: false);
        $reviewer->authorizeBrowser($browser);
        $browser->jsonRequest('POST', $this->path($request->getInstanceId(), $taskId), array_replace($this->payload(), ['expectedVersion' => 1]));
        $this->assertError($browser, 409, ApprovalFlowException::INSTANCE_VERSION_CONFLICT);
        static::assertSame('pending', static::getContainer()->get(Connection::class)->fetchOne('SELECT status FROM approval_flow_task WHERE id = :id', ['id' => Uuid::fromHexToBytes($taskId)]));
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidPayloads')]
    public function testMalformedPayloadsAreRejected(array $changes): void
    {
        $browser = $this->getBrowser(permissions: [ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $browser->jsonRequest('POST', $this->path(Uuid::randomHex(), Uuid::randomHex()), array_replace($this->payload(), $changes));
        static::assertSame(400, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public static function invalidPayloads(): iterable
    {
        yield 'invalid outcome' => [['outcome' => 'cancelled']];
        yield 'invalid command id' => [['commandId' => 'invalid']];
        yield 'nonpositive version' => [['expectedVersion' => 0]];
        yield 'fractional version' => [['expectedVersion' => 2.5]];
        yield 'boolean version' => [['expectedVersion' => true]];
        yield 'null command id' => [['commandId' => null]];
        yield 'missing outcome' => [['outcome' => null]];
        yield 'wrong reason scalar' => [['reason' => 123]];
        yield 'wrong reason type' => [['reason' => []]];
        yield 'oversized reason' => [['reason' => str_repeat('批', 1001)]];
    }

    /**
     * @return array{commandId: string, expectedVersion: int, outcome: string}
     */
    private function payload(): array
    {
        return ['commandId' => Uuid::randomHex(), 'expectedVersion' => 2, 'outcome' => 'approved'];
    }

    private function path(string $instanceId, string $taskId): string
    {
        return '/api/_action/approval-flow/instances/' . $instanceId . '/tasks/' . $taskId . '/decide';
    }

    private function assertError(HttpKernelBrowser $browser, int $status, string $code): void
    {
        static::assertSame($status, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $response = json_decode((string) $browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame($code, $response['errors'][0]['code']);
    }

    /**
     * @return array{ApprovalRequestEntity, string, string}
     */
    private function createRequest(string $reviewerId, ?string $graph = null): array
    {
        $kernel = static::getContainer();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $releaseId = ApprovalRuntimeFixture::publish($kernel, $this->runtime, str_replace('finance-user', $reviewerId, $graph ?? GraphFixture::json()));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $context = Context::createDefaultContext($source);
        $requests = $this->runtime->get(ApprovalRequestService::class);
        $request = $requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'revision'), '{"amount":100,"currency":"CNY","recipient":"Li ••••1234","paymentAccount":"Merchant","reason":null}', $context);
        $requests->start($request->getId(), $context);
        $task = $kernel->get(Connection::class)->fetchAssociative('SELECT task.id, task.execution_id FROM approval_flow_task task INNER JOIN approval_flow_execution execution ON execution.id = task.execution_id WHERE execution.instance_id = :id', ['id' => Uuid::fromHexToBytes($request->getInstanceId())]);
        static::assertNotFalse($task);

        return [$request, Uuid::fromBytesToHex($task['id']), Uuid::fromBytesToHex($task['execution_id'])];
    }
}
