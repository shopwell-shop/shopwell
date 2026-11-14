<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionStatus;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimePreviewEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalWorkflowPreviewSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<WorkflowExecutionDefinitionCollection> $definitions
     */
    public function __construct(private EntityRepository $definitions, private ApprovalRoute $routes, private WorkflowInputSchema $forms, private WorkflowUserLookup $users)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowRuntimePreviewEvent::class => 'preview'];
    }

    public function preview(WorkflowRuntimePreviewEvent $event): void
    {
        if ($event->release->getPublisher() !== 'core:approval') {
            return;
        }
        $definition = $this->definitions->search(new Criteria([$event->release->getId()]), $event->context)->getEntities()->first();
        if ($definition === null || $definition->status !== WorkflowExecutionDefinitionStatus::PUBLISHED) {
            throw WorkflowException::publicationRejected('workflow_definition_unavailable');
        }
        $graph = GraphData::fromArray($definition->graph());
        $form = $this->forms->normalizeData($definition->formFields(), $event->data, $event->context);
        $path = $this->routes->path($graph, $form);
        $steps = [];
        $userIds = [];
        foreach ($path['nodeIds'] as $id) {
            $node = $graph->node($id);
            if (\in_array($node->type, [WorkflowNodeTypes::START, WorkflowNodeTypes::END], true)) {
                continue;
            }
            if ($node->type === WorkflowNodeTypes::CONDITION) {
                $edge = $this->routes->next($graph, $id, $form);
                $branch = $edge?->properties['name'] ?? null;
                $steps[] = ['nodeId' => $id, 'type' => 'condition', 'name' => $node->name(), 'rule' => null, 'assignees' => [], 'branch' => \is_string($branch) ? $branch : null];
                continue;
            }
            if ($node->type !== WorkflowNodeTypes::TASK || $node->property('assigneeType') !== WorkflowAssigneeTypes::USER) {
                throw WorkflowException::publicationRejected('preview_unavailable');
            }
            $ids = $node->property('assigneeValue', []);
            if (!\is_array($ids) || !array_is_list($ids) || $ids === []) {
                throw WorkflowException::publicationRejected('preview_unavailable');
            }
            $assignees = [];
            foreach ($ids as $userId) {
                if (!\is_string($userId)) {
                    throw WorkflowException::publicationRejected('preview_unavailable');
                }
                $userIds[] = $userId;
                $assignees[] = ['id' => $userId, 'name' => $userId];
            }
            $steps[] = ['nodeId' => $id, 'type' => 'approval', 'name' => $node->name(), 'rule' => WorkflowCountersignTypes::fromNode($node), 'assignees' => $assignees, 'branch' => null];
        }
        $names = $this->users->names($userIds, $event->context);
        foreach ($steps as &$step) {
            foreach ($step['assignees'] as &$assignee) {
                $assignee['name'] = $names[$assignee['id']] ?? $assignee['id'];
            }
            unset($assignee);
        }
        unset($step);
        $event->setSteps($steps);
    }
}
