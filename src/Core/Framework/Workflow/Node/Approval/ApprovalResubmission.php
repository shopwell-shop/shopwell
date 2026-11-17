<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEngine;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowTransactionRunner;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormService;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\ApprovalResubmissionTest
 */
#[Package('framework')]
final readonly class ApprovalResubmission
{
    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instances
     */
    public function __construct(
        private WorkflowService $workflows,
        private EntityRepository $instances,
        private WorkflowEngine $engine,
        private WorkflowTaskFinder $tasks,
        private WorkflowTaskStateHandler $taskStates,
        private WorkflowInstanceStateHandler $instanceStates,
        private WorkflowRecorder $recorder,
        private ApprovalFormService $forms,
        private WorkflowTransactionRunner $transactions,
        private WorkflowEventQueue $events,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Called only inside ApprovalWorkflowService's locked ownership boundary.
     */
    public function returnToInitiator(WorkflowTaskEntity $task, string $comment, WorkflowExecutionContext $execution): void
    {
        foreach ($this->tasks->forInstance($task->instanceId, $execution->context()) as $pending) {
            if ($pending->isTodo()) {
                $this->taskStates->cancel($pending->id, $execution->context());
            }
        }

        $this->engine->cancel($execution);
        $this->instanceStates->returnToInitiator($task->instanceId, $execution->context());
        $this->instances->update([['id' => $task->instanceId, 'currentNodeId' => null]], $execution->context());
        $this->recorder->recordTaskAction($task, ApprovalTaskActions::RETURN, $comment, [], $execution->context());
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function resubmit(string $instanceId, int $expectedRound, array $formData, string $comment, Context $context, ?int $formVersion = null): void
    {
        $actorId = $this->workflows->actorId($context);
        $this->transactions->run(function () use ($instanceId, $expectedRound, $formData, $comment, $context, $actorId, $formVersion): void {
            $this->transactions->lockInstance($instanceId);
            $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($instanceId, $expectedRound, $formData, $comment, $actorId, $formVersion): void {
                $instance = $this->workflows->loadInstance($instanceId, $scoped);

                if ($instance->initiatorId !== $actorId) {
                    throw WorkflowExecutionException::forbidden('Only the initiator can resubmit this instance.');
                }

                if ($instance->state() !== WorkflowInstanceStates::RETURNED || $instance->round !== $expectedRound) {
                    throw WorkflowExecutionException::resubmissionConflict($instanceId);
                }

                $graph = GraphData::fromArray($instance->snapshot());
                $schema = $graph->singleStartNode()->property('inputSchema');

                if (!\is_array($schema)) {
                    throw WorkflowExecutionException::invalidGraph('Resubmission requires a saved input schema.');
                }

                $normalized = $this->forms->resubmit($instance, $formData, $formVersion, $actorId, $scoped);
                $this->dispatcher->dispatch(new ApprovalResubmittingEvent($instance, $normalized, $scoped));

                $round = $instance->round + 1;
                $this->instances->update([[
                    'id' => $instanceId,
                    'formData' => $normalized,
                    'round' => $round,
                    'currentNodeId' => null,
                    'endedAt' => null,
                    'result' => null,
                ]], $scoped);
                $this->instanceStates->resubmit($instanceId, $scoped);
                $fresh = $this->workflows->loadInstance($instanceId, $scoped);
                $explanation = trim($comment);
                $this->recorder->record($instanceId, WorkflowInstanceStates::ACTION_RESUBMIT, null, $actorId, $explanation !== '' ? $explanation : null, [
                    'round' => $round,
                    'previousRound' => $instance->round,
                ], $scoped);
                $this->engine->start($fresh, new WorkflowExecutionContext($graph, $fresh, $scoped, $actorId, $round));
            });
        });
        $this->events->flush();
    }
}
