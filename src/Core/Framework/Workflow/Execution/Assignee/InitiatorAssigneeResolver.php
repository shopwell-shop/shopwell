<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 *
 * 发起人本人：`assigneeValue` 必须为空。
 *
 * 典型场景是"确认制"：发起之后自己再确认一次，或者用于"发起人签署"。
 * 会签里它是 1 个人，`countersignType` 无论配什么都不影响结果。
 */
#[Package('framework')]
final class InitiatorAssigneeResolver implements AssigneeResolverInterface
{
    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::INITIATOR;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        return $instance->initiatorId === '' ? [] : [$instance->initiatorId];
    }
}
