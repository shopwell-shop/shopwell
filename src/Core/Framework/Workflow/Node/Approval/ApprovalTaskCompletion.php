<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowTaskCompletedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalTaskCompletion
{
    public function __construct(
        private CounterSignCalculator $counterSigns,
        private WorkflowTaskStateHandler $taskStates,
        private WorkflowTaskFinder $tasks,
        private WorkflowRecorder $recorder,
        private WorkflowEventQueue $events,
        private WorkflowEngine $engine,
    ) {
    }

    public function complete(WorkflowTaskEntity $task, bool $agreed, ?string $comment, WorkflowExecutionContext $context): void
    {
        $executionId = $task->ext['nodeExecutionId'] ?? null;

        if (!\is_string($executionId)) {
            throw WorkflowExecutionException::executionNotWaiting($task->id);
        }

        if ($agreed) {
            $this->taskStates->agree($task->id, $context->context());
        } else {
            $this->taskStates->reject($task->id, $context->context());
        }

        $this->recorder->recordTaskCompletion($task, $agreed, $comment, $context->context());
        $this->events->push(new WorkflowTaskCompletedEvent($task->instanceId, $task->id, $task->nodeId, $task->assigneeId, $agreed, $comment));
        $siblings = $this->tasks->forNode($task->instanceId, $task->nodeId, $task->round, $context->context());
        $originals = $siblings->filter(static fn (WorkflowTaskEntity $sibling): bool => !self::isAddedReviewer($sibling));
        $added = $siblings->filter(static fn (WorkflowTaskEntity $sibling): bool => self::isAddedReviewer($sibling));
        $result = $this->resolveOriginalVotes($originals, $task, $context);

        if ($added->countByState(WorkflowTaskStates::REJECTED) > 0) {
            $result = CounterSignResult::rejected(0, 1, $siblings->count());
        }

        // Freeze the original group's result, including first-decision approval,
        // while required reviewers finish their independent votes.
        if (!$result->isPending()) {
            foreach ($originals as $original) {
                if ($original->isTodo()) {
                    $this->taskStates->cancel($original->id, $context->context());
                }
            }
        }

        if ($result->isAgreed() && $added->countByState(WorkflowTaskStates::TODO) > 0) {
            return;
        }

        if ($result->isPending() && self::isAddedReviewer($task)) {
            // Only an original sequential task may advance the original assignee list.
            return;
        }

        if (!$result->isPending()) {
            foreach ($added as $reviewer) {
                if ($reviewer->isTodo()) {
                    $this->taskStates->cancel($reviewer->id, $context->context());
                }
            }
        }

        $this->engine->resume($executionId, new WorkflowSignal(WorkflowSignal::TASK_COMPLETED, [
            'taskId' => $task->id,
            'result' => $result->status(),
        ]), $context);
    }

    private function resolveOriginalVotes(WorkflowTaskCollection $originals, WorkflowTaskEntity $task, WorkflowExecutionContext $context): CounterSignResult
    {
        $expectedTotal = null;
        foreach ($originals as $original) {
            if ($original->assigneeSnapshot() !== []) {
                $expectedTotal = \count($original->assigneeSnapshot());
                break;
            }
        }
        $node = $context->graph()->node($task->nodeId);

        return $this->counterSigns->resolve(
            $originals,
            $task->countersignType,
            $expectedTotal,
            $node->property('approvalModeConfig', []),
            $node->property('approvalModeVersion', 1)
        );
    }

    private static function isAddedReviewer(WorkflowTaskEntity $task): bool
    {
        return ($task->ext[ApprovalTaskActions::EXT_PARTICIPATION] ?? null) === ApprovalTaskActions::REQUIRED;
    }
}
