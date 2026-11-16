<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowAssigneeSources;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\WorkflowException;

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
        if ($configuration !== []) {
            throw WorkflowException::invalidDocument('', 'Department leaders are resolved from the initiator primary department.');
        }

        return new ApprovalAssignment(WorkflowAssigneeTypes::UNIT_LEADER, [], multiple: true);
    }
}
