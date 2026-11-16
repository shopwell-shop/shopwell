<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 */
#[Package('framework')]
class DepartmentLeaderAssigneeResolver implements AssigneeResolverInterface
{
    public function __construct(private readonly OrganizationAssigneeLookup $lookup)
    {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::UNIT_LEADER;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $primary = $this->lookup->primaryMembership($instance->initiatorId, $context->context());
        if ($primary === null) {
            return [];
        }
        $memberships = $this->lookup->departmentLeaders($primary->getOrganizationUnitId(), $context->context());
        $userIds = [];
        foreach ($memberships as $membership) {
            $this->lookup->assertEligible($membership, $node->id, $this->getAssigneeType());
            $userIds[] = $membership->getUserId();
        }

        return array_values(array_unique($userIds));
    }
}
