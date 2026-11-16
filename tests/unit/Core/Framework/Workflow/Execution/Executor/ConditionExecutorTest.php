<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Executor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Executor\ConditionExecutor;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConditionExecutor::class)]
class ConditionExecutorTest extends TestCase
{
    public function testConditionContinuesWithoutCreatingApprovalTasks(): void
    {
        $executor = new ConditionExecutor();
        $instance = new WorkflowInstanceEntity();
        $node = new GraphNode('condition', WorkflowNodeTypes::CONDITION, 0, 0, []);
        $context = new WorkflowExecutionContext(GraphData::fromArray(['nodes' => [], 'edges' => []]), $instance, Context::createDefaultContext(), actorId: null, round: 1);
        static::assertSame(WorkflowNodeTypes::CONDITION, $executor->getNodeType());
        static::assertTrue($executor->enter($instance, $node, $context)->isContinue());
        static::assertTrue($executor->resume($instance, $node, new WorkflowSignal('test:resume'), $context)->isContinue());
    }
}
