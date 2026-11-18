<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Executor\ScriptExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptExecutor::class)]
class ScriptExecutorTest extends TestCase
{
    public function testEvaluatesOnlyThePublishedExpressionContext(): void
    {
        $instance = new WorkflowInstanceEntity();
        $instance->formData = ['amount' => '12'];
        $graph = GraphData::fromArray(['nodes' => [['id' => 'script', 'type' => 'snaker:script', 'properties' => ['scriptExpression' => 'form["amount"] + 1']]], 'edges' => []]);
        $outcome = (new ScriptExecutor())->enter($instance, $graph->node('script'), new WorkflowExecutionContext($graph, $instance, Context::createDefaultContext(), null, 1));
        static::assertSame(['result' => 13], $outcome->output);
    }

    public function testRejectsExpressionsThatTryToUseUnavailableNames(): void
    {
        $instance = new WorkflowInstanceEntity();
        $graph = GraphData::fromArray(['nodes' => [['id' => 'script', 'type' => 'snaker:script', 'properties' => ['scriptExpression' => 'form[']]], 'edges' => []]);
        $this->expectException(WorkflowExecutionException::class);
        (new ScriptExecutor())->enter($instance, $graph->node('script'), new WorkflowExecutionContext($graph, $instance, Context::createDefaultContext(), null, 1));
    }
}
