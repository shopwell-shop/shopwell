<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutor;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestEntity;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalBindingSubscriber;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalRequestStartTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private Connection $connection;

    private ApprovalRequestService $requests;

    private ApprovalRequestEntity $request;

    private Context $context;

    private string $reviewerId;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->reviewerId = TestUser::createNewTestUser($this->connection)->getUserId();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-start-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($container, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $this->requests = $this->runtime->get(ApprovalRequestService::class);
        $graph = str_replace('finance-user', $this->reviewerId, GraphFixture::json());
        $releaseId = ApprovalRuntimeFixture::publish($container, $this->runtime, $graph);
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $this->context = Context::createDefaultContext($source);
        $this->request = $this->requests->create(
            'payment:transfer',
            Uuid::randomHex(),
            1,
            hash('sha256', 'revision'),
            '{"amount":100,"currency":"CNY","recipient":"Li ••••1234","paymentAccount":"Merchant","reason":null}',
            $this->context,
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testStartsTheFrozenRequestAndCreatesItsFirstTasksAtomically(): void
    {
        $receipt = $this->requests->start($this->request->getId(), $this->context);

        static::assertSame(ApprovalInstanceStatus::Running, $receipt->status);
        static::assertSame(2, $receipt->aggregateVersion);
        static::assertSame($this->request->getInstanceId(), $receipt->instanceId);
        static::assertSame($this->request->getId(), $receipt->commandId);
        $rows = $this->connection->fetchAllAssociative('SELECT node_id, status FROM approval_flow_execution ORDER BY `sequence`');
        static::assertSame([
            ['node_id' => 'start', 'status' => 'completed'],
            ['node_id' => 'review', 'status' => 'awaiting_approval'],
        ], $rows);
        static::assertSame(Uuid::fromHexToBytes($this->reviewerId), $this->connection->fetchOne('SELECT assignee_id FROM approval_flow_task WHERE status = :status', ['status' => 'pending']));
        $operation = $this->connection->fetchAssociative('SELECT operation, previous_status, actor_json FROM approval_flow_operation');
        static::assertNotFalse($operation);
        static::assertSame('start', $operation['operation']);
        static::assertSame('draft', $operation['previous_status']);
        static::assertSame($this->request->getInitiatorId(), json_decode($operation['actor_json'], associative: true, flags: \JSON_THROW_ON_ERROR)['userId']);
        static::assertSame(Context::USER_SCOPE, $this->context->getScope());
    }

    public function testRetryReturnsTheOriginalStartEvenAfterSuspensionOrDeploymentFailure(): void
    {
        $receipt = $this->requests->start($this->request->getId(), $this->context);
        $this->runtime->get(ApprovalInstanceService::class)->suspend($receipt->instanceId, Uuid::randomHex(), 2, 'Manual review.', Context::createDefaultContext());
        $this->filesystem->remove($this->directory);

        static::assertEquals($receipt, $this->requests->start($this->request->getId(), $this->context));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation'));
    }

    public function testUnavailableReviewerLeavesTheRequestDraftAndCanBeRetried(): void
    {
        static::getContainer()->get('user.repository')->update([['id' => $this->reviewerId, 'active' => false]], Context::createDefaultContext());
        try {
            $this->requests->start($this->request->getId(), $this->context);
            static::fail('Unavailable reviewers must not produce a running instance without tasks.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::ASSIGNEE_UNAVAILABLE, $exception->getErrorCode());
        }
        static::assertSame('draft', $this->connection->fetchOne('SELECT status FROM approval_flow_instance'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation'));
        static::getContainer()->get('user.repository')->update([['id' => $this->reviewerId, 'active' => true]], Context::createDefaultContext());
        static::assertSame(2, $this->requests->start($this->request->getId(), $this->context)->aggregateVersion);
    }

    public function testAnAdmittedRequestKeepsItsReleaseWhenTheActiveBindingChanges(): void
    {
        $otherReviewer = TestUser::createNewTestUser($this->connection)->getUserId();
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, str_replace('finance-user', $otherReviewer, GraphFixture::json()));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 1, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 2, 1, Context::createDefaultContext());

        $this->requests->start($this->request->getId(), $this->context);

        static::assertSame(Uuid::fromHexToBytes($this->reviewerId), $this->connection->fetchOne('SELECT assignee_id FROM approval_flow_task'));
    }

    public function testTheFirstTaskCanBeDecidedThroughTheExistingPersonalCommand(): void
    {
        $receipt = $this->requests->start($this->request->getId(), $this->context);
        $taskId = $this->connection->fetchOne('SELECT id FROM approval_flow_task');
        static::assertIsString($taskId);
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);

        $decision = $this->runtime->get(ApprovalInstanceService::class)->decide(
            $receipt->instanceId,
            Uuid::fromBytesToHex($taskId),
            Uuid::randomHex(),
            2,
            ApprovalOutcome::Approved,
            'Checked.',
            Context::createDefaultContext($source),
        );

        static::assertSame(3, $decision->aggregateVersion);
        static::assertSame('approved', $this->connection->fetchOne('SELECT status FROM approval_flow_task'));
        static::assertSame('completed', $this->connection->fetchOne('SELECT status FROM approval_flow_execution WHERE node_id = :node', ['node' => 'review']));
    }

    public function testUnknownRequestUsesTheStableNotFoundError(): void
    {
        $id = Uuid::randomHex();
        $this->expectExceptionObject(ApprovalFlowException::requestNotFound($id));

        $this->requests->start($id, $this->context);
    }

    public function testTaggedAdvanceNodesRunBeforeTheFirstApprovalWithoutCoreChanges(): void
    {
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory, [PreparationNodeCompiler::class, PreparationNodeExecutor::class]);
        $runtime = ApprovalRuntimeFixture::container(static::getContainer(), $manifest, static function (ContainerBuilder $runtime): void {
            $runtime->register(PreparationNodeLoader::class)->addTag('approval_flow.node_type_loader');
            $runtime->register(PreparationNodeCompiler::class)->addTag('approval_flow.node_compiler');
            $runtime->register(PreparationNodeExecutor::class)->addTag('approval_flow.node_executor')->setPublic(true);
            $runtime->getDefinition(NodeTypeRegistry::class)->setPublic(true);
        });
        // Exercise the real business binding subscriber against this deployment's extended catalog.
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PaymentTransferApprovalBindingSubscriber($runtime->get(NodeTypeRegistry::class)));
        $runtime->set('event_dispatcher', $dispatcher);
        $graph = json_decode(str_replace('finance-user', $this->reviewerId, GraphFixture::json()), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph);
        static::assertIsArray($graph->nodes);
        static::assertIsArray($graph->connections);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[1]);
        static::assertInstanceOf(\stdClass::class, $graph->connections[0]);
        $node = clone $graph->nodes[1];
        $node->type = PreparationNodeLoader::TYPE;
        $node->config = (object) ['label' => 'Validate'];
        $node->id = 'validate';
        $graph->nodes[] = $node;
        $node = clone $node;
        $node->config = (object) ['label' => 'Prepare'];
        $node->id = 'prepare';
        $graph->nodes[] = $node;
        $graph->connections[0]->target = 'validate';
        $edge = clone $graph->connections[0];
        $edge->id = 'validate-prepare';
        $edge->source = 'validate';
        $edge->target = 'prepare';
        $graph->connections[] = $edge;
        $edge = clone $edge;
        $edge->id = 'prepare-review';
        $edge->source = 'prepare';
        $edge->target = 'review';
        $graph->connections[] = $edge;
        $draft = $runtime->get(ApprovalDraftService::class)->create('Preparation extension', json_encode($graph, \JSON_THROW_ON_ERROR), Context::createDefaultContext());
        $compiled = $runtime->get(ApprovalReleaseCompiler::class)->compile($draft->graph);
        $releaseId = $runtime->get(ApprovalReleaseService::class)->publish($draft->definitionId, $draft->revision, $compiled->hash, Context::createDefaultContext())->id;
        $bindings = $runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 1, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 2, 1, Context::createDefaultContext());
        $requests = $runtime->get(ApprovalRequestService::class);
        $request = $requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'prepared'), $this->request->getFormJson(), $this->context);

        $receipt = $requests->start($request->getId(), $this->context);
        $requests->start($request->getId(), $this->context);

        static::assertSame(ApprovalInstanceStatus::Running, $receipt->status);
        static::assertSame(['start', 'validate', 'prepare', 'review'], $this->connection->fetchFirstColumn('SELECT node_id FROM approval_flow_execution WHERE instance_id = :id ORDER BY `sequence`', ['id' => Uuid::fromHexToBytes($receipt->instanceId)]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
        $executor = $runtime->get(PreparationNodeExecutor::class);
        static::assertInstanceOf(PreparationNodeExecutor::class, $executor);
        static::assertSame(['Validate', 'Prepare'], $executor->enteredLabels);
    }

    public function testAnUnsupportedInitialSplitLeavesNoPartialExecutions(): void
    {
        $graph = json_decode(str_replace('finance-user', $this->reviewerId, GraphFixture::json()), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph);
        static::assertIsArray($graph->nodes);
        static::assertIsArray($graph->connections);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[1]);
        static::assertInstanceOf(\stdClass::class, $graph->connections[0]);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[0]);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[2]);
        static::assertIsArray($graph->nodes[0]->ports);
        static::assertIsArray($graph->nodes[2]->ports);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[0]->ports[0]);
        static::assertInstanceOf(\stdClass::class, $graph->nodes[2]->ports[0]);
        static::assertInstanceOf(\stdClass::class, $graph->connections[1]);
        $graph->nodes[0]->ports[0]->cardinality = 'many';
        $graph->nodes[2]->ports[0]->cardinality = 'many';
        $review = clone $graph->nodes[1];
        $review->id = 'other-review';
        $graph->nodes[] = $review;
        $edge = clone $graph->connections[0];
        $edge->id = 'start-other';
        $edge->target = 'other-review';
        $graph->connections[] = $edge;
        $edge = clone $graph->connections[1];
        $edge->id = 'other-end';
        $edge->source = 'other-review';
        $graph->connections[] = $edge;
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $releaseId, 1, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 2, 1, Context::createDefaultContext());
        $request = $this->requests->create('payment:transfer', Uuid::randomHex(), 1, hash('sha256', 'split'), $this->request->getFormJson(), $this->context);
        $this->expectExceptionObject(ApprovalFlowException::unsupportedStartRoute($releaseId));

        try {
            $this->requests->start($request->getId(), $this->context);
        } finally {
            static::assertSame('draft', $this->connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($request->getInstanceId())]));
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation'));
        }
    }

    public function testAnotherUserCannotStartAnAdmittedRequest(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::requestInitiatorRequired($this->request->getId()));
        $this->requests->start($this->request->getId(), Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testFailedTaskListenerRollsBackTheWholeStart(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_task.written', static function (): void {
            throw new \RuntimeException('Task listener failed.');
        }, once: true);
        try {
            $this->requests->start($this->request->getId(), $this->context);
            static::fail('A failed task write must roll back activation and executions.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Task listener failed.', $exception->getMessage());
        }
        static::assertSame('draft', $this->connection->fetchOne('SELECT status FROM approval_flow_instance'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::assertSame(2, $this->requests->start($this->request->getId(), $this->context)->aggregateVersion);
    }

    public function testExecutionListenerCannotReenterTheSameStartCommand(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_execution.written', function (): void {
            $this->requests->start($this->request->getId(), $this->context);
        }, once: true);
        try {
            $this->requests->start($this->request->getId(), $this->context);
            static::fail('A row lock does not prevent recursive commands on the same connection.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::INSTANCE_COMMAND_IN_PROGRESS, $exception->getErrorCode());
        }
        static::assertSame('draft', $this->connection->fetchOne('SELECT status FROM approval_flow_instance'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::assertSame(2, $this->requests->start($this->request->getId(), $this->context)->aggregateVersion);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class PreparationNodeLoader implements NodeTypeLoader
{
    public const TYPE = 'test:prepare';

    public function load(): array
    {
        return [new NodeTypeSpecification(
            self::TYPE,
            '1.0.0',
            'test.prepare',
            self::class,
            '{"$id":"urn:test:prepare:1.0.0","type":"object","required":["label"],"properties":{"label":{"type":"string"}},"additionalProperties":false}',
            ['in' => 'input', 'out' => 'output'],
            [NodeEffectType::Advance],
        )];
    }
}

/**
 * @internal
 */
#[Package('framework')]
final readonly class PreparationNodeConfiguration implements NodeConfiguration
{
    public function __construct(public string $label)
    {
    }

    public function jsonSerialize(): array
    {
        return ['label' => $this->label];
    }
}

/**
 * @internal
 */
#[Package('framework')]
class PreparationNodeCompiler implements NodeCompiler
{
    public function getTypes(): array
    {
        return [PreparationNodeLoader::TYPE => ['1.0.0']];
    }

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        $config = json_decode($configurationJson, flags: \JSON_THROW_ON_ERROR);
        if (!$config instanceof \stdClass || !\is_string($config->label ?? null)) {
            throw ApprovalFlowException::invalidNodeConfiguration($type, $version, '/label', 'Expected a label.');
        }

        return new PreparationNodeConfiguration($config->label);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class PreparationNodeExecutor implements NodeExecutor
{
    /**
     * @var list<string>
     */
    public array $enteredLabels = [];

    public function getType(): string
    {
        return PreparationNodeLoader::TYPE;
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function enter(NodeConfiguration $configuration): AdvanceEffect
    {
        if (!$configuration instanceof PreparationNodeConfiguration) {
            throw new \LogicException('Expected a preparation configuration.');
        }
        $this->enteredLabels[] = $configuration->label;

        return new AdvanceEffect();
    }
}
