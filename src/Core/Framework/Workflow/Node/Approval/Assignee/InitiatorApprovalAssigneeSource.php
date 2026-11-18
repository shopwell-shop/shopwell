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
final class InitiatorApprovalAssigneeSource implements ApprovalAssigneeSource
{
    public function getName(): string
    {
        return WorkflowAssigneeSources::INITIATOR;
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        if ($configuration !== []) {
            throw WorkflowException::invalidDocument('', 'The initiator source does not accept configuration.');
        }

        return new ApprovalAssignment(WorkflowAssigneeTypes::INITIATOR, [], multiple: false);
    }
}
