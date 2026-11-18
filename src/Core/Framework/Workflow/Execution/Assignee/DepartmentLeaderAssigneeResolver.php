<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalHierarchy;

/**
 * @internal
 */
#[Package('framework')]
class DepartmentLeaderAssigneeResolver implements AssigneeResolverInterface
{
    public function __construct(
        private readonly OrganizationAssigneeLookup $lookup,
        private readonly OrganizationAssigneeContext $organizations,
    ) {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::UNIT_LEADER;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $hierarchy = ApprovalHierarchy::fromConfiguration($node->property('assigneeConfig', []));
        $departmentId = $this->organizations->departmentId($instance);
        $organizationId = null;
        $seen = [];
        $userIds = [];
        for ($level = 1; $level <= $hierarchy->level; ++$level) {
            if ($departmentId === null) {
                return [];
            }
            if (isset($seen[$departmentId])) {
                throw WorkflowExecutionException::invalidGraph('The department hierarchy contains a cycle.');
            }
            $seen[$departmentId] = true;
            $department = $this->lookup->department($departmentId, $context->context());
            if ($department === null || !$department->isActive() || !$department->getOrganization()?->isActive()) {
                throw WorkflowExecutionException::noAssignee($node->id, $this->getAssigneeType());
            }
            $organizationId ??= $department->getOrganizationId();
            if ($department->getOrganizationId() !== $organizationId) {
                throw WorkflowExecutionException::invalidGraph('A department hierarchy cannot cross organization boundaries.');
            }
            if ($hierarchy->continuous || $level === $hierarchy->level) {
                $memberships = $this->lookup->departmentLeaders($departmentId, $context->context());
                if ($memberships === []) {
                    return [];
                }
                foreach ($memberships as $membership) {
                    $this->lookup->assertEligible($membership, $node->id, $this->getAssigneeType());
                    $userIds[] = $membership->getUserId();
                }
            }
            $departmentId = $department->getParentId();
        }

        return array_values(array_unique($userIds));
    }
}
