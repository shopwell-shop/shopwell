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
 * @internal
 */
#[Package('framework')]
final class ConditionExecutor implements NodeExecutorInterface
{
    public function getNodeType(): string
    {
        return WorkflowNodeTypes::CONDITION;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        return NodeOutcome::proceed();
    }

    public function resume(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        WorkflowSignal $signal,
        WorkflowExecutionContext $context,
    ): NodeOutcome {
        // 条件节点不产生任务；路由始终由引擎按出边顺序执行。
        return NodeOutcome::proceed();
    }
}
