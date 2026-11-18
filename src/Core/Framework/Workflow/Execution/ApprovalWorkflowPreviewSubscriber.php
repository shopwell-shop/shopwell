<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionStatus;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceMetadataEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Node\Approval\Automation\ApprovalAutomation;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Preview\ApprovalNodePreviewEvent;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimePreviewEvent;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        private ApprovalAssigneeResolver $assignees,
        private ApprovalModeRegistry $modes,
        private EventDispatcherInterface $events,
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
        $metadata = new WorkflowInstanceMetadataEvent($actorId, $event->context);
        $this->events->dispatch($metadata);
        $instance->ext = $metadata->getMetadata();
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

            $nodePreview = new ApprovalNodePreviewEvent($node, $executionContext);
            $this->previewBuiltInNode($nodePreview);
            $this->events->dispatch($nodePreview);
            $preview = $nodePreview->getPreview();

            if ($preview === null) {
                throw WorkflowException::publicationRejected('preview_unavailable');
            }

            $assignees = [];

            foreach ($preview['assigneeIds'] as $userId) {
                $userIds[] = $userId;
                $assignees[] = ['id' => $userId, 'name' => $userId];
            }

            $steps[] = [
                'nodeId' => $id,
                'type' => $preview['type'],
                'name' => $node->name(),
                'rule' => $preview['rule'],
                'assignees' => $assignees,
                'branch' => $preview['branch'],
            ];
            if ($node->type === WorkflowNodeTypes::TASK && $preview['rule'] === 'reject' && $preview['assigneeIds'] === []) {
                break;
            }
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

    private function previewBuiltInNode(ApprovalNodePreviewEvent $event): void
    {
        $node = $event->node;
        $execution = $event->execution;

        if ($node->type === WorkflowNodeTypes::CONDITION) {
            $edge = $this->routes->next($execution->graph(), $node->id, $execution->instance()->form());
            $branch = $edge?->properties['name'] ?? null;
            $event->setPreview('condition', branch: \is_string($branch) ? $branch : null);

            return;
        }

        if ($node->type !== WorkflowNodeTypes::TASK || !\is_string($node->property('assigneeType'))) {
            return;
        }

        $mode = $this->modes->forNode($node);
        $policy = ApprovalAutomation::fromConfiguration(\array_key_exists('approvalAutomation', $node->properties) ? $node->properties['approvalAutomation'] : []);
        if ($policy->decision !== 'manual') {
            $event->setPreview('approval', [], $policy->decision);

            return;
        }
        try {
            $assignment = $this->assignees->resolve($execution->instance(), $node, $execution);
        } catch (WorkflowExecutionException $exception) {
            if ($exception->getErrorCode() !== WorkflowExecutionException::NO_ASSIGNEE) {
                throw $exception;
            }

            throw WorkflowException::publicationRejected('approval_assignee_unavailable');
        }

        $event->setPreview('approval', $assignment->assigneeIds, $assignment->automaticDecision ?? $mode->getType());
    }
}
