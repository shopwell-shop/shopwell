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
final class PositionApprovalAssigneeSource implements ApprovalAssigneeSource
{
    public function getName(): string
    {
        return WorkflowAssigneeSources::POSITIONS;
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        $ids = $configuration['positionIds'] ?? null;
        if (array_keys($configuration) !== ['positionIds'] || !\is_array($ids) || !array_is_list($ids) || $ids === [] || \count($ids) > 50) {
            throw WorkflowException::invalidDocument('/positionIds', 'Select between one and fifty positions.');
        }

        $positionIds = [];
        foreach ($ids as $index => $id) {
            if (!\is_string($id) || !Uuid::isValid(strtolower($id)) || \in_array(strtolower($id), $positionIds, true)) {
                throw WorkflowException::invalidDocument('/positionIds/' . $index, 'A position must have a unique valid ID.');
            }
            $positionIds[] = strtolower($id);
        }

        return new ApprovalAssignment(WorkflowAssigneeTypes::POSITION, $positionIds, multiple: true);
    }
}
