<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;

/**
 * Validate required properties and installed resolver capabilities without resolving users or roles.
 *
 * @internal
 */
#[Package('framework')]
final class NodePropertiesRule implements GraphRuleInterface
{
    public function __construct(private readonly AssigneeResolverRegistry $resolvers)
    {
    }

    public function validate(GraphData $graph, ValidationContext $context): array
    {
        $violations = [];

        foreach ($graph->nodeList() as $node) {
            $violations = [...$violations, ...match ($node->type) {
                WorkflowNodeTypes::TASK => $this->validateTask($node),
                WorkflowNodeTypes::COPY => $this->validateCopy($node),
                // Start and end nodes have no required configuration.
                default => [],
            }];
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function validateTask(GraphNode $node): array
    {
        $violations = [];

        if (trim((string) $node->property('name', '')) === '') {
            $violations[] = Violation::error('WORKFLOW_EXECUTION_006', 'An approval node must have a name.', $node->id);
        }

        $countersignType = $node->property('countersignType');

        if (!\is_string($countersignType) || !\in_array($countersignType, WorkflowCountersignTypes::ALL, true)) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                \sprintf('Invalid approval mode on the approval node (current value: %s).', $this->describe($countersignType)),
                $node->id
            );
        }

        $assigneeType = $node->property('assigneeType');

        if (!\is_string($assigneeType) || !\in_array($assigneeType, WorkflowAssigneeTypes::ALL, true)) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                \sprintf('Invalid assignee type on the approval node (current value: %s).', $this->describe($assigneeType)),
                $node->id
            );

            return $violations;
        }

        if (!$this->resolvers->has($assigneeType)) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                \sprintf('The resolver for assignee type "%s" is unavailable.', $assigneeType),
                $node->id
            );

            return $violations;
        }

        if (!\in_array($assigneeType, WorkflowAssigneeTypes::NEEDS_VALUE, true)) {
            return $violations;
        }

        $value = $node->property('assigneeValue');

        // Validate nonempty string IDs here; the runtime resolvers check whether they exist.
        $isEmpty = !\is_array($value) || $value === [] || array_filter(
            $value,
            static fn (mixed $item): bool => \is_string($item) && $item !== ''
        ) === [];

        if ($isEmpty) {
            $violations[] = Violation::error(
                'WORKFLOW_EXECUTION_006',
                $assigneeType === WorkflowAssigneeTypes::ROLE ? 'Select an approval role.' : 'Select an assignee.',
                $node->id
            );
        }

        return $violations;
    }

    /**
     * @return list<Violation>
     */
    private function validateCopy(GraphNode $node): array
    {
        $violations = [];

        if (trim((string) $node->property('name', '')) === '') {
            $violations[] = Violation::error('WORKFLOW_EXECUTION_006', 'A copy node must have a name.', $node->id);
        }

        $userIds = $node->property('userIdList');

        if (!\is_array($userIds) || $userIds === []) {
            // Treat missing copy recipients as a configuration error.
            $violations[] = Violation::error('WORKFLOW_EXECUTION_006', 'A copy node must have at least one recipient.', $node->id);
        }

        return $violations;
    }

    private function describe(mixed $value): string
    {
        return \is_string($value) ? ($value === '' ? 'empty' : $value) : get_debug_type($value);
    }
}
