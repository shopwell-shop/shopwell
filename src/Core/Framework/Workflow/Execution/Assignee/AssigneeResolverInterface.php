<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal Experimental execution extension point; not covered by the backward compatibility promise.
 *
 * 审批人解析器：一种 `assigneeType` 一个实现。
 *
 * 返回**用户 id 列表**而不是实体：引擎只需要 id 去建任务，
 * 拉实体只会多一次 hydration。需要姓名时由 Presenter 批量补。
 *
 * 约定：
 * - 结果必须去重且**保持业务顺序**（顺序会签依赖它决定谁先审）；
 * - 返回空数组表示"这种配置下确实没有人"，调用方会抛"解析不到审批人"，
 *   不要为了"让流程跑起来"返回一个兜底用户（比如管理员）——那是越权。
 */
#[Package('framework')]
interface AssigneeResolverInterface
{
    /**
     * {@see \Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes} 之一
     */
    public function getAssigneeType(): string;

    /**
     * @return list<string>
     */
    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array;
}
