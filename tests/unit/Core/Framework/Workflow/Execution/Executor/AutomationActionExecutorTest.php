<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowAutomationActionEvent;
use Shopwell\Core\Framework\Workflow\Execution\Executor\AutomationActionExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AutomationActionExecutor::class)]
class AutomationActionExecutorTest extends TestCase
{
    public function testDispatchesActionAndReturnsHandlerOutput(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkflowAutomationActionEvent::class, static fn (WorkflowAutomationActionEvent $event) => $event->complete(['id' => 'done']));
        $graph = GraphData::fromArray(['nodes' => [['id' => 'action', 'type' => 'snaker:action', 'properties' => ['automationAction' => 'example:sync', 'automationInput' => ['key' => 'value']]]], 'edges' => []]);
        $instance = new WorkflowInstanceEntity();
        $outcome = (new AutomationActionExecutor('snaker:action', $dispatcher))->enter($instance, $graph->node('action'), new WorkflowExecutionContext($graph, $instance, Context::createDefaultContext(), null, 1));
        static::assertSame(['id' => 'done'], $outcome->output);
    }

    public function testRejectsAnActionWithoutAHandler(): void
    {
        $graph = GraphData::fromArray(['nodes' => [['id' => 'action', 'type' => 'snaker:action', 'properties' => ['automationAction' => 'example:sync', 'automationInput' => []]]], 'edges' => []]);
        $this->expectException(WorkflowExecutionException::class);
        (new AutomationActionExecutor('snaker:action', new EventDispatcher()))->enter(new WorkflowInstanceEntity(), $graph->node('action'), new WorkflowExecutionContext($graph, new WorkflowInstanceEntity(), Context::createDefaultContext(), null, 1));
    }
}
