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
 *
 * 开始节点。
 *
 * 什么也不做，直接往下走——发起动作（建实例、写日志、派事件）在
 * {@see \Shopwell\Core\Framework\Workflow\Execution\WorkflowService::start()} 里，因为那时实例还没建出来，
 * 执行器拿不到 `$instance`。
 *
 * 保留这个执行器而不是让引擎对开始节点特殊处理：引擎对节点类型必须无感，
 * 否则每加一种节点都要在引擎里加一个 `if`。
 */
#[Package('framework')]
final class StartExecutor implements NodeExecutorInterface
{
    public function getNodeType(): string
    {
        return WorkflowNodeTypes::START;
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
        // 开始节点不产生任务，永远不会走到这里。真走到说明图被改坏了。
        return NodeOutcome::proceed();
    }
}
