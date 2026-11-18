<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Automation;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;

/**
 * @internal
 */
#[Package('framework')]
final readonly class AutomaticApprovalResolver
{
    /**
     * @param EntityRepository<WorkflowTaskHistoryCollection> $history
     * @param EntityRepository<WorkflowNodeExecutionCollection> $executions
     */
    public function __construct(private EntityRepository $history, private EntityRepository $executions)
    {
    }

    /**
     * @param list<string> $assignees
     *
     * @return array<string, string> User ID to automatic approval reason.
     */
    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, array $assignees, Context $context): array
    {
        $policy = ApprovalAutomation::fromConfiguration(\array_key_exists('approvalAutomation', $node->properties) ? $node->properties['approvalAutomation'] : []);
        if ((!$policy->approveInitiator && $policy->deduplication === 'none') || $this->requiresReviewInput($instance, $node)) {
            return [];
        }
        $automatic = [];
        if ($policy->approveInitiator && \in_array($instance->initiatorId, $assignees, true)) {
            $automatic[$instance->initiatorId] = 'initiator';
        }
        if ($policy->deduplication === 'none') {
            return $automatic;
        }

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('instanceId', $instance->id),
            new EqualsFilter('round', $instance->round),
            new EqualsFilter('decision', 'agree'),
        )->addSorting(new FieldSorting('completedAt'), new FieldSorting('id'));
        $history = $this->history->search($criteria, $context)->getEntities();
        $previousNodeId = $policy->deduplication === 'adjacent' ? $this->previousApprovalNode($instance, $history, $context) : null;
        foreach ($history as $approval) {
            if ($policy->deduplication === 'adjacent' && $approval->nodeId !== $previousNodeId) {
                continue;
            }
            if (\in_array($approval->assigneeId, $assignees, true)) {
                $automatic[$approval->assigneeId] ??= 'previous-approval';
            }
        }

        return $automatic;
    }

    private function previousApprovalNode(WorkflowInstanceEntity $instance, WorkflowTaskHistoryCollection $history, Context $context): ?string
    {
        if ($history->count() === 0) {
            return null;
        }
        $nodeIds = array_values(array_unique(array_map(static fn ($approval): string => $approval->nodeId, $history->getElements())));
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('instanceId', $instance->id),
            new EqualsFilter('round', $instance->round),
            new EqualsFilter('status', WorkflowNodeExecutionEntity::COMPLETED),
            new EqualsAnyFilter('nodeId', $nodeIds),
        )->addSorting(new FieldSorting('sequence', FieldSorting::DESCENDING))->setLimit(1);

        return $this->executions->search($criteria, $context)->getEntities()->first()?->nodeId;
    }

    private function requiresReviewInput(WorkflowInstanceEntity $instance, GraphNode $node): bool
    {
        $permissions = $node->property('approvalFormPermissions', []);
        if (!\is_array($permissions)) {
            throw WorkflowExecutionException::invalidGraph('Invalid approval form permissions.');
        }
        foreach ($permissions as $key => $permission) {
            if (!\is_array($permission)) {
                throw WorkflowExecutionException::invalidGraph('Invalid approval form permission.');
            }
            if (($permission['required'] ?? false) !== true) {
                continue;
            }
            $value = $instance->form()[$key] ?? null;
            if ($value === null || $value === [] || (\is_string($value) && trim($value) === '')) {
                return true;
            }
        }

        return false;
    }
}
