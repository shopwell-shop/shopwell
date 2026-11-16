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
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\TaskExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionStore;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\CycleRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\NodePropertiesRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ReachabilityRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleEndRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleStartRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNumberGenerator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowRoute;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;

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
        $installed = $this->service(new AssigneeResolverRegistry([new BoundaryOrganizationResolver($type)]));
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
        $withoutExtension = $this->service(new AssigneeResolverRegistry([]));

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

    /**
     * @return iterable<string, array{string}>
     */
    public static function organizationSources(): iterable
    {
        yield 'initiator manager resolver' => ['leader'];
        yield 'unit manager resolver' => ['unitLeader'];
    }

    private function service(AssigneeResolverRegistry $resolvers): WorkflowService
    {
        $container = static::getContainer();
        $events = $container->get(WorkflowEventQueue::class);
        $states = $container->get(WorkflowInstanceStateHandler::class);
        $clock = $container->get(ClockInterface::class);
        $executors = new NodeExecutorRegistry([
            new StartExecutor(),
            new EndExecutor(),
            new TaskExecutor($resolvers, $container->get('workflow_task.repository'), $events, $container->get(WorkflowTaskStateHandler::class)),
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
            new NodePropertiesRule($resolvers),
        ]);

        return new WorkflowService(
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
        );
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
