<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowAutomationActionEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatch one integration action and continue only after a registered handler confirms it.
 *
 * @internal
 */
#[Package('framework')]
final readonly class AutomationActionExecutor implements NodeExecutorInterface
{
    public function __construct(private string $nodeType, private EventDispatcherInterface $dispatcher)
    {
    }

    public function getNodeType(): string
    {
        return $this->nodeType;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        $action = $node->property('automationAction');
        $input = $node->property('automationInput', []);
        if (!\is_string($action) || !\is_array($input)) {
            throw WorkflowExecutionException::invalidGraph('Integration nodes require a valid action and input map.');
        }
        $event = new WorkflowAutomationActionEvent($node, $context, $action, $input);
        $this->dispatcher->dispatch($event);
        if (!$event->handled) {
            throw WorkflowExecutionException::invalidGraph('No handler is installed for workflow action: ' . $action);
        }

        return NodeOutcome::proceed(output: $event->output);
    }

    public function resume(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowSignal $signal, WorkflowExecutionContext $context): NodeOutcome
    {
        return $this->enter($instance, $node, $context);
    }
}
