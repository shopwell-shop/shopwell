<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * Resolve the initiator as the sole assignee; assigneeValue must be empty.
 *
 * @internal
 */
#[Package('framework')]
final class InitiatorAssigneeResolver implements AssigneeResolverInterface
{
    public function __construct(private readonly UserAssigneeResolver $users)
    {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::INITIATOR;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        if ($instance->initiatorId === '') {
            return [];
        }

        $assignment = new GraphNode($node->id, $node->type, $node->x, $node->y, [...$node->properties, 'assigneeValue' => [$instance->initiatorId]]);

        return $this->users->resolve($instance, $assignment, $context);
    }
}
