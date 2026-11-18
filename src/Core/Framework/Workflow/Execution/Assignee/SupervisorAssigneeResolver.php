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
class SupervisorAssigneeResolver implements AssigneeResolverInterface
{
    public function __construct(
        private readonly OrganizationAssigneeLookup $lookup,
        private readonly OrganizationAssigneeContext $organizations,
    ) {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::LEADER;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $hierarchy = ApprovalHierarchy::fromConfiguration($node->property('assigneeConfig', []));
        $departmentId = $this->organizations->departmentId($instance);
        if ($departmentId === null || $instance->initiatorId === '') {
            return [];
        }

        $membership = $this->lookup->membershipForDepartment($instance->initiatorId, $departmentId, $context->context());
        $organizationId = $membership?->getOrganizationUnit()?->getOrganizationId();
        if ($membership === null || $organizationId === null) {
            return [];
        }

        $department = $membership->getOrganizationUnit();
        if (!$department?->isActive() || !$department->getOrganization()?->isActive()) {
            throw WorkflowExecutionException::noAssignee($node->id, $this->getAssigneeType());
        }

        $seen = [$membership->getId() => true];
        $seenUsers = [$instance->initiatorId => true];
        $assignees = [];
        for ($level = 1; $level <= $hierarchy->level; ++$level) {
            $supervisorId = $membership->getSupervisorMembershipId();
            if ($supervisorId === null) {
                return [];
            }
            if (isset($seen[$supervisorId])) {
                throw WorkflowExecutionException::invalidGraph('The reporting hierarchy contains a cycle.');
            }
            $seen[$supervisorId] = true;
            $membership = $this->lookup->membership($supervisorId, $context->context());
            if ($membership === null) {
                return [];
            }
            if ($membership->getOrganizationUnit()?->getOrganizationId() !== $organizationId) {
                throw WorkflowExecutionException::invalidGraph('A reporting hierarchy cannot cross organization boundaries.');
            }
            if (isset($seenUsers[$membership->getUserId()])) {
                throw WorkflowExecutionException::invalidGraph('The reporting hierarchy contains a user cycle.');
            }
            $seenUsers[$membership->getUserId()] = true;
            $this->lookup->assertEligible($membership, $node->id, $this->getAssigneeType());
            if ($hierarchy->continuous || $level === $hierarchy->level) {
                $assignees[] = $membership->getUserId();
            }
        }

        return $assignees;
    }
}
