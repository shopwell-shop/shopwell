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
final class FormFieldApprovalAssigneeSource implements ApprovalAssigneeSource
{
    public function getName(): string
    {
        return WorkflowAssigneeSources::FORM_FIELD;
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        $key = $configuration['fieldKey'] ?? null;
        if (array_keys($configuration) !== ['fieldKey'] || !\is_string($key) || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $key) !== 1) {
            throw WorkflowException::invalidDocument('/fieldKey', 'Select a people field from the request form.');
        }

        return new ApprovalAssignment(WorkflowAssigneeTypes::FORM_FIELD, [$key], multiple: true);
    }
}
