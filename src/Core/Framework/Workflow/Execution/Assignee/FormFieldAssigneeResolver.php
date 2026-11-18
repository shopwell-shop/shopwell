<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowEntityFields;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 */
#[Package('framework')]
final readonly class FormFieldAssigneeResolver implements AssigneeResolverInterface
{
    public function __construct(private UserAssigneeResolver $users)
    {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::FORM_FIELD;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $reference = $node->property('assigneeValue');
        if (!\is_array($reference) || !array_is_list($reference) || \count($reference) !== 1 || !\is_string($reference[0])) {
            throw WorkflowExecutionException::invalidGraph('Select one people field for the approval assignment.');
        }

        $fields = (new WorkflowInputSchema())->normalize($context->graph()->singleStartNode()->property('inputSchema', []));
        $field = null;
        foreach ($fields as $candidate) {
            if ($candidate['key'] === $reference[0]) {
                $field = $candidate;
                break;
            }
        }
        if ($field === null || !\in_array($field['type'], ['user', 'users'], true)) {
            throw WorkflowExecutionException::invalidGraph('The approval assignment must reference a published people field.');
        }

        $value = $instance->form()[$field['key']] ?? null;
        if ($value === null || $value === '' || ($field['type'] === 'users' && $value === [])) {
            return [];
        }

        $ids = WorkflowEntityFields::identifiers($field['type'] === 'users' ? $value : [$value], 50);
        if (isset($field['candidateIds']) && array_diff($ids, $field['candidateIds']) !== []) {
            throw WorkflowExecutionException::invalidGraph('The selected reviewer is outside the published candidate range.');
        }

        $assignment = new GraphNode($node->id, $node->type, $node->x, $node->y, [...$node->properties, 'assigneeValue' => $ids]);

        return $this->users->resolve($instance, $assignment, $context);
    }
}
