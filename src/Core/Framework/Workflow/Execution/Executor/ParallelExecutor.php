<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * Opens all outgoing branch paths; WorkflowEngine owns the fan-out scheduling.
 *
 * @internal
 */
#[Package('framework')]
final class ParallelExecutor implements NodeExecutorInterface
{
    public function getNodeType(): string
    {
        return WorkflowNodeTypes::PARALLEL;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::proceed();
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        return $this->enter($instance, $node, $context);
    }
}
