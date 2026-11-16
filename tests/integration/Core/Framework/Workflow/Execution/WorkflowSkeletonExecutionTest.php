<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ComparisonConditionEvaluator;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Executor\EndExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Executor\StartExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionStore;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\CycleRule;
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
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowSkeletonExecutionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Context $context;

    protected function setUp(): void
    {
        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class));
        $this->context = new Context(new AdminApiSource($user->getUserId()));
    }

    public function testRegisteredAutomaticNodeRunsWithoutApprovalExecutorsOrSubscribers(): void
    {
        $flows = $this->service(new SkeletonAutomaticExecutor());
        $instanceId = $this->start($flows, 'test:automatic');
        $instance = $flows->loadInstance($instanceId, $this->context);
        $executions = $this->executions($instanceId);

        static::assertSame('completed', $instance->state());
        static::assertSame('completed', $instance->result);
        static::assertSame(['start', 'custom', 'end'], array_column($executions->getElements(), 'nodeId'));
        static::assertSame([1, 2, 3], array_column($executions->getElements(), 'sequence'));
        static::assertSame([null, 'enter-custom', 'leave-custom'], array_column($executions->getElements(), 'sourceConnectionId'));
        static::assertSame(['reference' => 'external-42'], $executions->filterByProperty('nodeId', 'custom')->first()?->output);
        static::assertSame(0, static::getContainer()->get('workflow_task.repository')->search((new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId)), $this->context)->getTotal());
    }

    public function testPersistedWaitResumesInAFreshRuntimeAndConsumesOnlyOnce(): void
    {
        $flows = $this->service(new SkeletonWaitingExecutor());
        $instanceId = $this->start($flows, 'test:wait');
        $execution = $this->executions($instanceId)->filterByProperty('nodeId', 'custom')->first();
        static::assertNotNull($execution);
        static::assertSame('waiting', $execution->status);
        static::assertSame('test:response', $execution->waitingFor);

        $freshRuntime = $this->service(new SkeletonWaitingExecutor());
        $freshRuntime->signal($instanceId, $execution->id, new WorkflowSignal('test:response', ['reference' => 'response-7']), $this->context);
        static::assertSame('completed', $freshRuntime->loadInstance($instanceId, $this->context)->state());
        static::assertCount(3, $this->executions($instanceId));
        static::assertSame(['reference' => 'response-7'], $this->executions($instanceId)->get($execution->id)?->output);

        $this->expectExceptionObject(WorkflowExecutionException::instanceNotRunning($instanceId));
        $freshRuntime->signal($instanceId, $execution->id, new WorkflowSignal('test:response'), $this->context);
    }

    public function testWrongSignalCannotConsumeTheSubscription(): void
    {
        $flows = $this->service(new SkeletonWaitingExecutor());
        $instanceId = $this->start($flows, 'test:wait');
        $execution = $this->executions($instanceId)->filterByProperty('nodeId', 'custom')->first();
        static::assertNotNull($execution);

        try {
            $flows->signal($instanceId, $execution->id, new WorkflowSignal('test:unrelated'), $this->context);
            static::fail('An unrelated signal must be rejected.');
        } catch (WorkflowExecutionException $exception) {
            static::assertSame('FRAMEWORK__WORKFLOW_EXECUTION_INVALID_SIGNAL', $exception->getErrorCode());
        }

        static::assertSame('waiting', $this->executions($instanceId)->get($execution->id)?->status);
        $flows->signal($instanceId, $execution->id, new WorkflowSignal('test:response'), $this->context);
        static::assertSame('completed', $flows->loadInstance($instanceId, $this->context)->state());
    }

    public function testCancellationInvalidatesThePersistedWait(): void
    {
        $flows = $this->service(new SkeletonWaitingExecutor());
        $instanceId = $this->start($flows, 'test:wait');
        $execution = $this->executions($instanceId)->filterByProperty('nodeId', 'custom')->first();
        static::assertNotNull($execution);
        $flows->cancel($instanceId, 'No longer needed', $this->context);

        $canceled = $this->executions($instanceId)->get($execution->id);
        static::assertNotNull($canceled);
        static::assertSame('canceled', $canceled->status);
        static::assertNull($canceled->waitingFor);
        $this->expectExceptionObject(WorkflowExecutionException::instanceNotRunning($instanceId));
        $flows->signal($instanceId, $execution->id, new WorkflowSignal('test:response'), $this->context);
    }

    public function testPublicationRejectsANodeWithoutAnExecutor(): void
    {
        $flows = $this->service(new SkeletonAutomaticExecutor());
        $definitionId = $flows->createDefinition(['code' => Uuid::randomHex(), 'name' => 'Unavailable node', 'graphData' => $this->graph('test:missing')], $this->context);

        $this->expectException(WorkflowExecutionException::class);
        $flows->publishDefinition($definitionId, $this->context);
    }

    private function service(NodeExecutorInterface $custom): WorkflowService
    {
        $container = static::getContainer();
        $events = $container->get(WorkflowEventQueue::class);
        $registry = new NodeExecutorRegistry([new StartExecutor(), new EndExecutor(), $custom]);
        $transactions = $container->get(WorkflowTransactionRunner::class);
        $clock = $container->get(ClockInterface::class);
        $states = $container->get(WorkflowInstanceStateHandler::class);
        $engine = new WorkflowEngine(
            $registry,
            new WorkflowRoute(new ComparisonConditionEvaluator(new ExprNormalizer())),
            new WorkflowNodeExecutionStore($container->get('workflow_node_execution.repository'), $clock),
            $states,
            $events,
            new EventDispatcher(),
            $container->get('workflow_instance.repository'),
        );
        $validator = new WorkflowGraphValidator([new ShapeRule($registry), new SingleStartRule(), new SingleEndRule(), new CycleRule(), new ReachabilityRule()]);

        return new WorkflowService(
            $container->get('workflow_execution_definition.repository'),
            $container->get('workflow_instance.repository'),
            $engine,
            $validator,
            $transactions,
            $events,
            $container->get(WorkflowRecorder::class),
            new WorkflowNumberGenerator(),
            $states,
            new WorkflowInputSchema(),
            $clock,
            $container->get('translator'),
        );
    }

    private function start(WorkflowService $flows, string $type): string
    {
        $definitionId = $flows->createDefinition(['code' => Uuid::randomHex(), 'name' => 'Skeleton validation', 'graphData' => $this->graph($type)], $this->context);
        $flows->publishDefinition($definitionId, $this->context);

        return $flows->start($definitionId, [], '', $this->context);
    }

    /**
     * @return array<string, mixed>
     */
    private function graph(string $customType): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'snaker:start'],
                ['id' => 'custom', 'type' => $customType],
                ['id' => 'end', 'type' => 'snaker:end'],
            ],
            'edges' => [
                ['id' => 'enter-custom', 'sourceNodeId' => 'start', 'targetNodeId' => 'custom'],
                ['id' => 'leave-custom', 'sourceNodeId' => 'custom', 'targetNodeId' => 'end', 'properties' => ['sourcePort' => 'out']],
            ],
        ];
    }

    private function executions(string $instanceId): WorkflowNodeExecutionCollection
    {
        $executions = static::getContainer()->get('workflow_node_execution.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('instanceId', $instanceId))->addSorting(new FieldSorting('sequence')),
            $this->context,
        )->getEntities();
        static::assertInstanceOf(WorkflowNodeExecutionCollection::class, $executions);

        return $executions;
    }
}

/**
 * @internal
 */
final class SkeletonAutomaticExecutor implements NodeExecutorInterface
{
    public function getNodeType(): string
    {
        return 'test:automatic';
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::proceed('out', ['reference' => 'external-42']);
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        throw new \LogicException('Automatic nodes do not wait.');
    }
}

/**
 * @internal
 */
final class SkeletonWaitingExecutor implements NodeExecutorInterface
{
    public function getNodeType(): string
    {
        return 'test:wait';
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::wait('test:response');
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::proceed('out', $signal->payload);
    }
}
