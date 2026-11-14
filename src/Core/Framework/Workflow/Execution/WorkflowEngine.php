<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowCompletingEvent;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceEndedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphEdge;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionStore;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal Sequential scheduling; parallel joins and subprocess scopes are not supported by this scheduler.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\WorkflowSkeletonExecutionTest
 */
#[Package('framework')]
final readonly class WorkflowEngine
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instances
     */
    public function __construct(
        private NodeExecutorRegistry $executors,
        private WorkflowRoute $routes,
        private WorkflowNodeExecutionStore $executions,
        private WorkflowInstanceStateHandler $instanceStates,
        private WorkflowEventQueue $events,
        private EventDispatcherInterface $dispatcher,
        private EntityRepository $instances,
    ) {
    }

    public function start(WorkflowInstanceEntity $instance, WorkflowExecutionContext $context): void
    {
        $this->advance($context->graph()->singleStartNode(), $context);
    }

    public function resume(string $executionId, WorkflowSignal $signal, WorkflowExecutionContext $context): void
    {
        $execution = $this->executions->waiting($executionId, $context->instance()->id, $signal, $context->context());
        $node = $context->graph()->node($execution->nodeId);
        $nodeContext = $context->withNodeExecution($executionId);
        $outcome = $this->executors->for($node->type)->resume($context->instance(), $node, $signal, $nodeContext);
        $this->executions->record($executionId, $outcome, $context->context());
        $this->continueFrom($node, $outcome, $context);
    }

    public function cancel(WorkflowExecutionContext $context): void
    {
        $this->executions->cancel($context->instance()->id, $context->context());
    }

    private function advance(GraphNode $node, WorkflowExecutionContext $context, ?string $sourceConnectionId = null): void
    {
        $visited = [];

        while (true) {
            if (isset($visited[$node->id])) {
                throw WorkflowExecutionException::invalidGraph('Sequential execution cannot contain a cycle.');
            }

            $visited[$node->id] = true;
            $executionId = $this->executions->enter($node, $context, $sourceConnectionId);
            $nodeContext = $context->withNodeExecution($executionId);
            $this->instances->update([['id' => $context->instance()->id, 'currentNodeId' => $node->id]], $context->context());
            $outcome = $this->executors->for($node->type)->enter($context->instance(), $node, $nodeContext);
            $this->executions->record($executionId, $outcome, $context->context());

            if (!$outcome->isContinue()) {
                $this->finishIfComplete($outcome, $context);

                return;
            }

            $edge = $this->nextEdge($node, $outcome, $context);
            $sourceConnectionId = $edge->id;
            $node = $context->graph()->node($edge->targetNodeId);
        }
    }

    private function continueFrom(GraphNode $node, NodeOutcome $outcome, WorkflowExecutionContext $context): void
    {
        if ($outcome->isContinue()) {
            $edge = $this->nextEdge($node, $outcome, $context);
            $this->advance($context->graph()->node($edge->targetNodeId), $context, $edge->id);

            return;
        }

        $this->finishIfComplete($outcome, $context);
    }

    private function nextEdge(GraphNode $node, NodeOutcome $outcome, WorkflowExecutionContext $context): GraphEdge
    {
        $edge = $this->routes->next($context->graph(), $node->id, $context->instance()->form(), $outcome->port);

        if ($edge === null) {
            throw WorkflowExecutionException::deadEnd($node->id);
        }

        return $edge;
    }

    private function finishIfComplete(NodeOutcome $outcome, WorkflowExecutionContext $context): void
    {
        if (!$outcome->isComplete()) {
            return;
        }

        $result = $outcome->result ?? 'completed';
        $completion = new WorkflowCompletingEvent($result, $context);
        $this->dispatcher->dispatch($completion);

        if (!$completion->handled) {
            $this->instanceStates->complete($context->instance()->id, $result, $context->context());
        }

        $this->executions->cancel($context->instance()->id, $context->context());
        $this->events->push(new WorkflowInstanceEndedEvent($context->instance()->id, $result));
    }
}
