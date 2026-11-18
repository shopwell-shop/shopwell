<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowAssigneeSources;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final class RoleApprovalAssigneeSource implements ApprovalAssigneeSource
{
    public function getName(): string
    {
        return WorkflowAssigneeSources::ROLES;
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        $ids = $configuration['roleIds'] ?? null;
        if (array_keys($configuration) !== ['roleIds'] || !\is_array($ids) || !array_is_list($ids) || $ids === [] || \count($ids) > 50) {
            throw WorkflowException::invalidDocument('/roleIds', 'Select between one and fifty roles.');
        }

        $roleIds = [];
        foreach ($ids as $index => $id) {
            if (!\is_string($id) || !Uuid::isValid(strtolower($id)) || \in_array(strtolower($id), $roleIds, true)) {
                throw WorkflowException::invalidDocument('/roleIds/' . $index, 'A role must have a unique valid ID.');
            }
            $roleIds[] = strtolower($id);
        }

        return new ApprovalAssignment(WorkflowAssigneeTypes::ROLE, $roleIds, multiple: true);
    }
}
