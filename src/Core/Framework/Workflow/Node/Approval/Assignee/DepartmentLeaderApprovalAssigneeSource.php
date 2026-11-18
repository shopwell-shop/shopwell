<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowAssigneeSources;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;

/**
 * @internal
 */
#[Package('framework')]
final class DepartmentLeaderApprovalAssigneeSource implements ApprovalAssigneeSource
{
    public function getName(): string
    {
        return WorkflowAssigneeSources::DEPARTMENT_LEADERS;
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        $hierarchy = ApprovalHierarchy::fromConfiguration($configuration);

        return new ApprovalAssignment(
            WorkflowAssigneeTypes::UNIT_LEADER,
            [],
            multiple: true,
            configuration: $configuration === [] ? [] : ['level' => $hierarchy->level, 'continuous' => $hierarchy->continuous],
        );
    }
}
