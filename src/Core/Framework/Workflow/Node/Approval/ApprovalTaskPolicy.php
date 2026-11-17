<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalTaskPolicy
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * @return list<string>
     */
    public function normalize(mixed $operations): array
    {
        $registration = new ApprovalOperationRegistrationEvent();
        $this->dispatcher->dispatch($registration);

        if (!\is_array($operations) || !array_is_list($operations)) {
            throw WorkflowExecutionException::invalidGraph('Approval operations must be a list.');
        }

        $names = [];
        foreach ($operations as $operation) {
            if (!\is_string($operation) || !\in_array($operation, $registration->names(), true) || \in_array($operation, $names, true)) {
                throw WorkflowExecutionException::invalidGraph('Approval operations must contain unique installed operation names.');
            }

            $names[] = $operation;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public function available(WorkflowTaskEntity $task, WorkflowInstanceEntity $instance, Context $context): array
    {
        if (!$task->isTodo() || !$instance->isRunning()) {
            return [];
        }

        if ($task->nodeId !== $instance->currentNodeId || $task->round !== $instance->round) {
            return [];
        }

        $source = $context->getSource();
        if ($source instanceof AdminApiSource && ($source->getUserId() !== $task->assigneeId || !$context->isAllowed('workflow_task:decide'))) {
            return [];
        }

        $node = GraphData::fromArray($instance->snapshot())->node($task->nodeId);
        $configured = \array_key_exists('approvalOperations', $node->properties)
            ? $node->properties['approvalOperations']
            : ApprovalTaskActions::OPERATIONS;

        // An unavailable plugin operation fails closed without hiding unrelated actions.
        $registration = new ApprovalOperationRegistrationEvent();
        $this->dispatcher->dispatch($registration);
        if (!\is_array($configured) || !array_is_list($configured)) {
            return [];
        }

        $actions = [];
        foreach ($configured as $name) {
            if (\is_string($name) && \in_array($name, $registration->names(), true)) {
                $actions[] = $name;
            }
        }

        $event = new ApprovalTaskActionsEvent($task, $instance, $context, $actions);
        $this->dispatcher->dispatch($event);

        return $event->actions();
    }

    public function assertAllowed(string $action, WorkflowTaskEntity $task, WorkflowInstanceEntity $instance, Context $context): void
    {
        if (!\in_array($action, $this->available($task, $instance, $context), true)) {
            throw WorkflowExecutionException::forbidden('This operation is not available for the current approval task.');
        }
    }
}
