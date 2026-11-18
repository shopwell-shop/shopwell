<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\MissingApprovalAssigneePolicy;

/**
 * Preview and execution share the same assignment and missing-person decisions.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalAssigneeResolver
{
    public function __construct(private AssigneeResolverRegistry $resolvers)
    {
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): ApprovalAssigneeResolution
    {
        $policy = MissingApprovalAssigneePolicy::fromConfiguration(\array_key_exists('missingAssignee', $node->properties) ? $node->properties['missingAssignee'] : []);
        $type = $node->property('assigneeType');
        if (!\is_string($type) || $type === '') {
            throw WorkflowExecutionException::unsupportedAssigneeType(get_debug_type($type));
        }

        $source = $this->resolvers->for($type);
        try {
            $ids = $source->resolve($instance, $node, $context);
        } catch (WorkflowExecutionException $exception) {
            if ($exception->getErrorCode() !== WorkflowExecutionException::NO_ASSIGNEE) {
                throw $exception;
            }
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter($ids, static fn (string $id): bool => $id !== '')));
        if ($ids !== []) {
            return new ApprovalAssigneeResolution($ids);
        }

        if ($policy->action === MissingApprovalAssigneePolicy::ASSIGN) {
            $replacement = new GraphNode($node->id, $node->type, $node->x, $node->y, [
                ...$node->properties,
                'assigneeType' => WorkflowAssigneeTypes::USER,
                'assigneeValue' => [$policy->userId],
            ]);
            $ids = $this->resolvers->for(WorkflowAssigneeTypes::USER)->resolve($instance, $replacement, $context);
            if ($ids !== []) {
                return new ApprovalAssigneeResolution($ids);
            }
        }
        if (\in_array($policy->action, [MissingApprovalAssigneePolicy::APPROVE, MissingApprovalAssigneePolicy::REJECT], true)) {
            return new ApprovalAssigneeResolution([], $policy->action);
        }

        throw WorkflowExecutionException::noAssignee($node->id, $type);
    }
}
