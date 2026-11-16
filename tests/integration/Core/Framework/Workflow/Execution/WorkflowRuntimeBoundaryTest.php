<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Connection\WorkflowConnection;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresenter;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\UserAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\TaskExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionStore;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\CycleRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\NodePropertiesRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ReachabilityRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleEndRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleStartRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCancellationStore;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNumberGenerator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowRoute;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalConfigurationCompiler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalTaskCompletion;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeSource;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssignment;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompilationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\ApprovalNodeRole;
use Shopwell\Core\Framework\Workflow\Publication\Approval\Node\CompiledApprovalNode;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowReleaseCheckEvent;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\QuorumApprovalMode;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowRuntimeBoundaryTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Context $context;

    private string $userId;

    protected function setUp(): void
    {
        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class));
        $this->userId = $user->getUserId();
        $this->context = new Context(new AdminApiSource($this->userId));
    }

    public function testGenericTaskRetainsItsCustomResultWithProductionApprovalSubscribers(): void
    {
        $container = static::getContainer();
        $workflows = $container->get(WorkflowService::class);
        $definitionId = $workflows->createDefinition([
            'code' => Uuid::randomHex(),
            'name' => 'Generic review with a custom result',
            'graphData' => $this->graph(assigneeType: 'initiator', result: 'custom-result'),
        ], $this->context);
        $workflows->publishDefinition($definitionId, $this->context);
        $instanceId = $workflows->start($definitionId, [], '', $this->context);
        $tasks = $container->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context);
        static::assertCount(1, $tasks);
        $task = $tasks->first();
        static::assertNotNull($task);

        $container->get(ApprovalWorkflowService::class)->approve($task->id, null, $this->context);

        $completed = $workflows->loadInstance($instanceId, $this->context);
        static::assertSame('completed', $completed->state());
        static::assertSame('custom-result', $completed->result);
        static::assertSame('agreed', $container->get(ApprovalWorkflowService::class)->loadTask($task->id, $this->context)->state());
    }

    #[DataProvider('organizationSources')]
    public function testRemovingAnInstalledResolverBlocksStartingThePublishedGraphBeforeWritingAnInstance(string $type): void
    {
        $installed = $this->runtime(new AssigneeResolverRegistry([new BoundaryOrganizationResolver($type)]))['workflows'];
        $definitionId = $installed->createDefinition([
            'code' => Uuid::randomHex(),
            'name' => 'Organization capability lifecycle',
            'graphData' => $this->graph(assigneeType: $type, result: 'completed'),
        ], $this->context);
        $installed->publishDefinition($definitionId, $this->context);
        $instanceId = $installed->start($definitionId, [], '', $this->context);
        $tasks = static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context);
        static::assertSame('published', $installed->loadDefinition($definitionId, $this->context)->status);
        static::assertSame('running', $installed->loadInstance($instanceId, $this->context)->state());
        static::assertCount(1, $tasks);
        static::assertSame([$this->userId], array_column($tasks->getElements(), 'assigneeId'));
        static::assertSame([$type], array_column($tasks->getElements(), 'assigneeType'));
        $withoutExtension = $this->runtime(new AssigneeResolverRegistry([]))['workflows'];

        try {
            $withoutExtension->start($definitionId, [], '', $this->context);
            static::fail('A published graph cannot start after its assignee capability is removed.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame(WorkflowExecutionException::GRAPH_INVALID, $exception->getErrorCode());
            static::assertSame('review', $exception->getParameters()['violations'][0]['nodeId']);
            static::assertSame('WORKFLOW_EXECUTION_006', $exception->getParameters()['violations'][0]['code']);
        }

        $instances = static::getContainer()->get('workflow_instance.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('definitionId', $definitionId)),
            $this->context,
        )->getEntities();
        static::assertSame([$instanceId], array_values($instances->getIds()));
        static::assertCount(1, static::getContainer()->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context));
    }

    public function testPluginNodePublishesAndExecutesItsSelectedPortFromTheSnapshot(): void
    {
        $container = static::getContainer();
        $container->get('user.repository')->update([['id' => $this->userId, 'admin' => true]], Context::createDefaultContext());
        $runtime = $this->runtime(new AssigneeResolverRegistry([new UserAssigneeResolver($container->get('user.repository'))]), additionalExecutors: [new BoundaryRouteExecutor()]);
        $codec = new WorkflowDocumentCodec(Validation::createValidator());
        $release = new WorkflowReleaseEntity();
        $release->assign([
            'id' => Uuid::randomHex(), 'revision' => 1, 'name' => 'Plugin routing node',
            'publisher' => ApprovalWorkflowPublisher::NAME,
            'document' => $codec->encode(new WorkflowDocument([
                new WorkflowNode('start', 'core:start', 1),
                new WorkflowNode('route', 'example:route', 2, ['selectedPort' => 'review']),
                new WorkflowNode('review', 'core:approval', 1, [
                    'assignee' => ['source' => 'core:user', 'config' => ['userId' => $this->userId]],
                    'approval' => ['mode' => 'core:single', 'config' => []],
                ]),
                new WorkflowNode('end', 'core:end', 1),
            ], [
                new WorkflowConnection('enter', 'start', 'out', 'route', 'request'),
                new WorkflowConnection('skip', 'route', 'skip', 'end', 'in'),
                new WorkflowConnection('review', 'route', 'review', 'review', 'in'),
                new WorkflowConnection('accept', 'review', 'approved', 'end', 'in'),
                new WorkflowConnection('reject', 'review', 'rejected', 'end', 'in'),
            ])),
        ]);
        $publisher = new ApprovalWorkflowPublisher(
            $codec,
            ApprovalRegistryFixture::compiler([new BoundaryRouteCompiler()]),
            $container->get('workflow_execution_definition.repository'),
            $container->get('user.repository'),
            $runtime['validator'],
        );
        $publisher->publish($release, $this->context);

        // Reading a published release does not require its publication compiler to remain installed.
        $withoutCompiler = new ApprovalWorkflowPublisher(
            $codec,
            ApprovalRegistryFixture::compiler(),
            $container->get('workflow_execution_definition.repository'),
            $container->get('user.repository'),
            $runtime['validator'],
        );
        $withoutCompiler->check(new WorkflowReleaseCheckEvent($release, $this->context));
        $instanceId = $runtime['workflows']->start($release->getId(), [], '', $this->context);
        $tasks = $container->get(WorkflowTaskFinder::class)->forInstance($instanceId, $this->context);
        static::assertCount(1, $tasks);
        $task = $tasks->first();
        static::assertNotNull($task);
        static::assertSame('review', $task->nodeId);
        $instance = $runtime['workflows']->loadInstance($instanceId, $this->context);
        static::assertSame(['type' => 'example:route', 'version' => 2], $instance->snapshot()['nodes'][1]['properties']['approvalNodeCompiler']);
        static::assertSame('review', $instance->snapshot()['nodes'][1]['properties']['selectedPort']);

        $runtime['approvals']->approve($task->id, 'Approved through plugin route', $this->context);
        static::assertSame('agreed', $runtime['workflows']->loadInstance($instanceId, $this->context)->state());

        $withoutExecutor = $this->runtime(new AssigneeResolverRegistry([new UserAssigneeResolver($container->get('user.repository'))]));
        $this->expectException(WorkflowExecutionException::class);
        $withoutExecutor['workflows']->start($release->getId(), [], '', $this->context);
    }

    public function testPluginSourceAndVersionedModeRunFromPublicationThroughFinalDecision(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $reviewerIds = [$this->userId];
        $reviewerIds[] = TestUser::createNewTestUser($connection)->getUserId();
        $reviewerIds[] = TestUser::createNewTestUser($connection)->getUserId();
        $container->get('user.repository')->update(array_map(
            static fn (string $id): array => ['id' => $id, 'admin' => true],
            $reviewerIds,
        ), Context::createDefaultContext());
        $modes = ApprovalRegistryFixture::modes([new QuorumApprovalMode()]);
        $runtime = $this->runtime(new AssigneeResolverRegistry([new BoundaryTeamResolver()]), $modes);
        $compiler = ApprovalRegistryFixture::compiler(approvals: new ApprovalConfigurationCompiler(
            new ApprovalAssigneeRegistry([new BoundaryTeamSource()]),
            $modes,
            new ExtensionDispatcher(new EventDispatcher()),
        ));
        $codec = new WorkflowDocumentCodec(Validation::createValidator());
        $release = new WorkflowReleaseEntity();
        $release->assign([
            'id' => Uuid::randomHex(), 'revision' => 1, 'name' => 'Plugin team approval',
            'publisher' => ApprovalWorkflowPublisher::NAME,
            'document' => $codec->encode(new WorkflowDocument([
                new WorkflowNode('start', 'core:start', 1),
                new WorkflowNode('review', 'core:approval', 1, [
                    'assignee' => ['source' => 'example:team', 'config' => ['userIds' => $reviewerIds]],
                    'approval' => ['mode' => 'example:quorum', 'version' => 2, 'config' => ['required' => 2]],
                ]),
                new WorkflowNode('end', 'core:end', 1),
            ], [
                new WorkflowConnection('begin', 'start', 'out', 'review', 'in'),
                new WorkflowConnection('accept', 'review', 'approved', 'end', 'in'),
                new WorkflowConnection('reject', 'review', 'rejected', 'end', 'in'),
            ])),
        ]);
        $publisher = new ApprovalWorkflowPublisher(
            $codec,
            $compiler,
            $container->get('workflow_execution_definition.repository'),
            $container->get('user.repository'),
            $runtime['validator'],
        );

        $publisher->publish($release, $this->context);
        $instanceId = $runtime['workflows']->start($release->getId(), [], '', $this->context);
        $finder = $container->get(WorkflowTaskFinder::class);
        $tasks = $finder->forInstance($instanceId, $this->context);
        static::assertCount(3, $tasks);
        static::assertSame(['example:team'], array_values(array_unique(array_column($tasks->getElements(), 'assigneeType'))));
        static::assertSame(['example:quorum'], array_values(array_unique(array_column($tasks->getElements(), 'countersignType'))));
        $byAssignee = [];
        foreach ($tasks as $task) {
            $byAssignee[$task->assigneeId] = $task->id;
        }

        $runtime['approvals']->approve($byAssignee[$reviewerIds[0]], 'First vote', $this->context);
        static::assertSame('running', $runtime['workflows']->loadInstance($instanceId, $this->context)->state());
        $runtime['approvals']->approve($byAssignee[$reviewerIds[1]], 'Threshold reached', new Context(new AdminApiSource($reviewerIds[1])));

        $instance = $runtime['workflows']->loadInstance($instanceId, $this->context);
        static::assertSame('agreed', $instance->state());
        static::assertNull($instance->result);
        static::assertSame(2, $instance->snapshot()['nodes'][1]['properties']['approvalModeVersion']);
        static::assertSame(['required' => 2], $instance->snapshot()['nodes'][1]['properties']['approvalModeConfig']);
        $detail = $container->get(WorkflowInstancePresenter::class)->present($instance, $this->context, withDetail: true);
        static::assertSame('approved', $detail['approvalStages'][0]['status']);
        static::assertEqualsCanonicalizing($reviewerIds, array_column($detail['approvalStages'][0]['participants'], 'id'));
        static::assertSame('canceled', $runtime['approvals']->loadTask($byAssignee[$reviewerIds[2]], $this->context)->state());
        $historyCriteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId));
        static::assertSame(2, $container->get('workflow_task_history.repository')->search($historyCriteria, $this->context)->getTotal());

        try {
            $runtime['approvals']->approve($byAssignee[$reviewerIds[1]], 'Duplicate decision', new Context(new AdminApiSource($reviewerIds[1])));
            static::fail('Completed tasks must not accept duplicate decisions.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame(WorkflowExecutionException::taskAlreadyHandled($byAssignee[$reviewerIds[1]])->getErrorCode(), $exception->getErrorCode());
        }
        static::assertSame(2, $container->get('workflow_task_history.repository')->search($historyCriteria, $this->context)->getTotal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function organizationSources(): iterable
    {
        yield 'initiator manager resolver' => ['leader'];
        yield 'unit manager resolver' => ['unitLeader'];
    }

    /**
     * @param list<NodeExecutorInterface> $additionalExecutors
     *
     * @return array{workflows: WorkflowService, approvals: ApprovalWorkflowService, validator: WorkflowGraphValidator}
     */
    private function runtime(AssigneeResolverRegistry $resolvers, ?ApprovalModeRegistry $modes = null, array $additionalExecutors = []): array
    {
        $modes ??= ApprovalRegistryFixture::modes();
        $container = static::getContainer();
        $events = $container->get(WorkflowEventQueue::class);
        $states = $container->get(WorkflowInstanceStateHandler::class);
        $clock = $container->get(ClockInterface::class);
        $executors = new NodeExecutorRegistry([
            new StartExecutor(),
            new EndExecutor(),
            ...$additionalExecutors,
            new TaskExecutor($resolvers, $container->get('workflow_task.repository'), $events, $container->get(WorkflowTaskStateHandler::class), $modes),
        ]);
        $engine = new WorkflowEngine(
            $executors,
            $container->get(WorkflowRoute::class),
            new WorkflowNodeExecutionStore($container->get('workflow_node_execution.repository'), $clock),
            $states,
            $events,
            $container->get('event_dispatcher'),
            $container->get('workflow_instance.repository'),
        );
        $validator = new WorkflowGraphValidator([
            new ShapeRule($executors),
            new SingleStartRule(),
            new SingleEndRule(),
            new CycleRule(),
            new ReachabilityRule(),
            new NodePropertiesRule($resolvers, $modes),
        ]);

        $workflows = new WorkflowService(
            $container->get('workflow_execution_definition.repository'),
            $container->get('workflow_instance.repository'),
            $engine,
            $validator,
            $container->get(WorkflowTransactionRunner::class),
            $events,
            $container->get(WorkflowRecorder::class),
            new WorkflowNumberGenerator(),
            $states,
            $container->get(WorkflowInputSchema::class),
            $clock,
            $container->get('translator'),
        );
        $approvals = new ApprovalWorkflowService(
            $workflows,
            $container->get('workflow_task.repository'),
            $container->get('workflow_copy.repository'),
            $container->get(WorkflowTransactionRunner::class),
            $events,
            $container->get(WorkflowTaskFinder::class),
            $container->get(WorkflowTaskStateHandler::class),
            $states,
            $container->get(WorkflowCancellationStore::class),
            new ApprovalTaskCompletion(
                new CounterSignCalculator($modes),
                $container->get(WorkflowTaskStateHandler::class),
                $container->get(WorkflowTaskFinder::class),
                $container->get(WorkflowRecorder::class),
                $events,
                $engine,
            ),
        );

        return ['workflows' => $workflows, 'approvals' => $approvals, 'validator' => $validator];
    }

    /**
     * @return array<string, mixed>
     */
    private function graph(string $assigneeType, string $result): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'snaker:start'],
                ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                    'name' => 'Review',
                    'assigneeType' => $assigneeType,
                    'countersignType' => 'single',
                ]],
                ['id' => 'end', 'type' => 'snaker:end', 'properties' => ['result' => $result]],
            ],
            'edges' => [
                ['id' => 'enter-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
                ['id' => 'leave-review', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
            ],
        ];
    }
}

/**
 * @internal
 */
final readonly class BoundaryOrganizationResolver implements AssigneeResolverInterface
{
    public function __construct(private string $type)
    {
    }

    public function getAssigneeType(): string
    {
        return $this->type;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        return [$instance->initiatorId];
    }
}

/**
 * @internal
 */
final class BoundaryTeamSource implements ApprovalAssigneeSource
{
    public function getName(): string
    {
        return 'example:team';
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        return new ApprovalAssignment('example:team', ['finance'], multiple: true, configuration: $configuration, knownUserIds: $configuration['userIds']);
    }
}

/**
 * @internal
 */
final class BoundaryTeamResolver implements AssigneeResolverInterface
{
    public function getAssigneeType(): string
    {
        return 'example:team';
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        return $node->property('assigneeConfig')['userIds'];
    }
}

/**
 * @internal
 */
final class BoundaryRouteCompiler implements ApprovalNodeCompiler
{
    public function getNodeType(): string
    {
        return 'example:route';
    }

    public function getNodeVersion(): int
    {
        return 2;
    }

    public function getRole(): ApprovalNodeRole
    {
        return ApprovalNodeRole::ACTIVITY;
    }

    public function compile(ApprovalNodeCompilationContext $context): CompiledApprovalNode
    {
        return new CompiledApprovalNode(
            'example:route',
            $context->configuration(['selectedPort']),
            inputPorts: ['request'],
            outputPorts: ['skip' => [], 'review' => []],
        );
    }
}

/**
 * @internal
 */
final class BoundaryRouteExecutor implements NodeExecutorInterface
{
    public function getNodeType(): string
    {
        return 'example:route';
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::proceed($node->property('selectedPort'));
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::proceed($node->property('selectedPort'));
    }
}
