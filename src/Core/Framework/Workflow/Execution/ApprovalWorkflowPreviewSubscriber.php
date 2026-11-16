<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionStatus;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
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
    public function __construct(
        private EntityRepository $definitions,
        private ApprovalRoute $routes,
        private WorkflowInputSchema $forms,
        private WorkflowUserLookup $users,
        private AssigneeResolverRegistry $resolvers,
        private ApprovalModeRegistry $modes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowRuntimePreviewEvent::class => 'preview'];
    }

    public function preview(WorkflowRuntimePreviewEvent $event): void
    {
        if ($event->release->getPublisher() !== ApprovalWorkflowPublisher::NAME) {
            return;
        }
        $definition = $this->definitions->search(new Criteria([$event->release->getId()]), $event->context)->getEntities()->first();

        if ($definition === null || $definition->status !== WorkflowExecutionDefinitionStatus::PUBLISHED) {
            throw WorkflowException::publicationRejected('workflow_definition_unavailable');
        }

        $graph = GraphData::fromArray($definition->graph());
        $form = $this->forms->normalizeData($definition->formFields(), $event->data, $event->context);
        $path = $this->routes->path($graph, $form);
        $source = $event->context->getSource();
        $actorId = $source instanceof AdminApiSource ? $source->getUserId() : null;
        $instance = new WorkflowInstanceEntity();
        $instance->initiatorId = $actorId ?? '';
        $instance->formData = $form;
        $instance->graphSnapshot = $definition->graph();
        $executionContext = new WorkflowExecutionContext($graph, $instance, $event->context, $actorId, 1);
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
                $steps[] = [
                    'nodeId' => $id,
                    'type' => 'condition',
                    'name' => $node->name(),
                    'rule' => null,
                    'assignees' => [],
                    'branch' => \is_string($branch) ? $branch : null,
                ];

                continue;
            }

            if ($node->type !== WorkflowNodeTypes::TASK || !\is_string($node->property('assigneeType'))) {
                throw WorkflowException::publicationRejected('preview_unavailable');
            }

            $mode = $this->modes->forNode($node);
            $ids = $this->resolvers->for($node->property('assigneeType'))->resolve($instance, $node, $executionContext);

            if ($ids === []) {
                throw WorkflowException::publicationRejected('approval_assignee_unavailable');
            }

            $assignees = [];
            foreach ($ids as $userId) {
                $userIds[] = $userId;
                $assignees[] = ['id' => $userId, 'name' => $userId];
            }

            $steps[] = [
                'nodeId' => $id,
                'type' => 'approval',
                'name' => $node->name(),
                'rule' => $mode->getType(),
                'assignees' => $assignees,
                'branch' => null,
            ];
        }

        $names = $this->users->names($userIds, $event->context);
        $namedSteps = [];
        foreach ($steps as $step) {
            $participants = [];
            foreach ($step['assignees'] as $assignee) {
                $participants[] = ['id' => $assignee['id'], 'name' => $names[$assignee['id']] ?? $assignee['id']];
            }
            $step['assignees'] = $participants;
            $namedSteps[] = $step;
        }

        $event->setSteps($namedSteps);
    }
}
