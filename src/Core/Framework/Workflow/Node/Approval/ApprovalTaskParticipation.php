<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\OrganizationAssigneeLookup;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowTaskCreatedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\Extension\ApprovalTaskParticipationExtension;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalTaskParticipation
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $tasks
     */
    public function __construct(
        private EntityRepository $tasks,
        private WorkflowTaskFinder $finder,
        private WorkflowTaskStateHandler $states,
        private OrganizationAssigneeLookup $assignees,
        private WorkflowRecorder $recorder,
        private WorkflowEventQueue $events,
        private ExtensionDispatcher $extensions,
    ) {
    }

    public function apply(WorkflowTaskEntity $task, string $action, string $targetUserId, string $comment, WorkflowExecutionContext $execution): void
    {
        if (!Uuid::isValid($targetUserId)) {
            throw WorkflowExecutionException::invalidGraph('The participant must be a valid user ID.');
        }
        $targetUserId = strtolower($targetUserId);
        $membership = $this->assignees->primaryMembership($targetUserId, $execution->context());
        if ($membership === null) {
            throw WorkflowExecutionException::noAssignee($task->nodeId, $task->assigneeType);
        }
        $this->assignees->assertEligible($membership, $task->nodeId, $task->assigneeType);

        $siblings = $this->finder->forNode($task->instanceId, $task->nodeId, $task->round, $execution->context());
        foreach ($siblings as $sibling) {
            if ($sibling->assigneeId === $targetUserId || \in_array($targetUserId, $sibling->assigneeSnapshot(), true)) {
                throw WorkflowExecutionException::invalidGraph('The user already participates in this approval stage.');
            }
        }

        $this->extensions->publish(
            ApprovalTaskParticipationExtension::NAME,
            new ApprovalTaskParticipationExtension($task, $action, $targetUserId, $comment, $execution),
            $this->create(...),
        );
    }

    private function create(WorkflowTaskEntity $task, string $action, string $targetUserId, string $comment, WorkflowExecutionContext $execution): string
    {
        if (!\in_array($action, [ApprovalTaskActions::TRANSFER, ApprovalTaskActions::ADD_SIGN], true)) {
            throw WorkflowExecutionException::invalidGraph('Unsupported approval participation action.');
        }

        $taskId = Uuid::randomHex();
        $metadata = $task->ext ?? [];
        $sequence = $task->sequence;
        if ($action === ApprovalTaskActions::TRANSFER) {
            $this->states->cancel($task->id, $execution->context());
        } else {
            // Added reviewers vote in a mandatory group, independent of the configured mode.
            $metadata = [
                'nodeExecutionId' => $metadata['nodeExecutionId'] ?? null,
                ApprovalTaskActions::EXT_PARTICIPATION => ApprovalTaskActions::REQUIRED,
            ];
            foreach ($this->finder->forNode($task->instanceId, $task->nodeId, $task->round, $execution->context()) as $sibling) {
                $sequence = max($sequence, $sibling->sequence);
            }
            ++$sequence;
        }

        $metadata['sourceTaskId'] = $task->id;
        $metadata[ApprovalTaskActions::EXT_CREATION_ACTION] = $action;
        $this->tasks->create([[
            'id' => $taskId,
            'instanceId' => $task->instanceId,
            'nodeId' => $task->nodeId,
            'nodeName' => $task->nodeName,
            'countersignType' => $task->countersignType,
            'round' => $task->round,
            'sequence' => $sequence,
            'assigneeId' => $targetUserId,
            'assigneeType' => $task->assigneeType,
            'stateId' => $this->states->initialStateId($execution->context()),
            'ext' => $metadata,
        ]], $execution->context());
        $this->recorder->recordTaskAction($task, $action, $comment, ['targetUserId' => $targetUserId, 'createdTaskId' => $taskId], $execution->context());
        $this->events->push(new WorkflowTaskCreatedEvent($taskId, $task->instanceId, $task->nodeId, $task->nodeName, $targetUserId, $task->countersignType));

        return $taskId;
    }
}
