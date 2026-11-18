<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * A join completes once every distinct incoming branch has reached it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class JoinExecutor implements NodeExecutorInterface
{
    /**
     * @param EntityRepository<WorkflowNodeExecutionCollection> $executions
     */
    public function __construct(private EntityRepository $executions)
    {
    }

    public function getNodeType(): string
    {
        return WorkflowNodeTypes::JOIN;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        $incoming = [];
        foreach ($context->graph()->edges() as $edge) {
            if ($edge->targetNodeId === $node->id) {
                $incoming[$edge->id] = true;
            }
        }
        $criteria = (new Criteria())->addFilter(new EqualsFilter('instanceId', $instance->id))->addFilter(new EqualsFilter('nodeId', $node->id));
        $arrived = [];
        foreach ($this->executions->search($criteria, $context->context())->getEntities() as $execution) {
            if ($execution->sourceConnectionId !== null && ($execution->status === WorkflowNodeExecutionEntity::WAITING || $execution->status === WorkflowNodeExecutionEntity::COMPLETED)) {
                $arrived[$execution->sourceConnectionId] = true;
            }
        }
        $current = $this->executions->search(new Criteria([$context->executionId()]), $context->context())->getEntities()->first();
        if ($current?->sourceConnectionId !== null) {
            $arrived[$current->sourceConnectionId] = true;
        }
        if (\count(array_intersect_key($incoming, $arrived)) < \count($incoming)) {
            return NodeOutcome::wait('workflow.parallel.join');
        }

        return NodeOutcome::proceed();
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        return $this->enter($instance, $node, $context);
    }
}
