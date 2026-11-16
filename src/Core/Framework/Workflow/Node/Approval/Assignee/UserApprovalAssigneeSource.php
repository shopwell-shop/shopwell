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
final readonly class UserApprovalAssigneeSource implements ApprovalAssigneeSource
{
    public function __construct(private bool $multiple)
    {
    }

    public function getName(): string
    {
        return $this->multiple ? WorkflowAssigneeSources::USERS : WorkflowAssigneeSources::USER;
    }

    public function compile(array $configuration): ApprovalAssignment
    {
        $key = $this->multiple ? 'userIds' : 'userId';

        if (array_keys($configuration) !== [$key]) {
            throw WorkflowException::invalidDocument('/' . $key, 'Select the approval participants.');
        }

        $ids = $this->multiple ? $configuration[$key] : [$configuration[$key]];

        if (!\is_array($ids) || !array_is_list($ids) || $ids === []) {
            throw WorkflowException::invalidDocument('/' . $key, 'Approval participants must be a nonempty list.');
        }

        $normalized = [];

        foreach ($ids as $index => $id) {
            if (!\is_string($id) || !Uuid::isValid(strtolower($id)) || \in_array(strtolower($id), $normalized, true)) {
                $path = '/' . $key;

                if ($this->multiple) {
                    $path .= '/' . $index;
                }

                throw WorkflowException::invalidDocument($path, 'An approval participant must have a unique valid user ID.');
            }

            $normalized[] = strtolower($id);
        }

        return new ApprovalAssignment(WorkflowAssigneeTypes::USER, $normalized, $this->multiple, knownUserIds: $normalized);
    }
}
