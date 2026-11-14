<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowCompletingEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalCompletionSubscriber implements EventSubscriberInterface
{
    public function __construct(private WorkflowTaskFinder $tasks, private WorkflowTaskStateHandler $taskStates, private WorkflowInstanceStateHandler $instanceStates)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowCompletingEvent::class => 'complete'];
    }

    public function complete(WorkflowCompletingEvent $event): void
    {
        if ($event->context->graph()->nodesOfType(WorkflowNodeTypes::TASK) === []) {
            return;
        }

        $instanceId = $event->context->instance()->id;
        $context = $event->context->context();

        foreach ($this->tasks->forInstance($instanceId, $context) as $task) {
            if ($task->isTodo()) {
                $this->taskStates->cancel($task->id, $context);
            }
        }

        if ($event->result === 'rejected') {
            $this->instanceStates->reject($instanceId, $context);
        } else {
            $this->instanceStates->agree($instanceId, $context);
        }

        $event->handled = true;
    }
}
