<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowTaskCompletedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;

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
        $expectedTotal = $task->countersignType === WorkflowCountersignTypes::ORDER_SIGN ? \count($task->assigneeSnapshot()) : null;
        $result = $this->counterSigns->resolve($siblings, $task->countersignType, $expectedTotal);

        if (!$result->isPending()) {
            foreach ($siblings as $sibling) {
                if ($sibling->isTodo()) {
                    $this->taskStates->cancel($sibling->id, $context->context());
                }
            }
        }

        $this->engine->resume($executionId, new WorkflowSignal('core:task-completed', [
            'taskId' => $task->id,
            'result' => $result->status(),
        ]), $context);
    }
}
