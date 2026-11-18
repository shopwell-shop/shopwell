<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Assignee;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\ApprovalAssigneeResolver;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalAssigneeResolver::class)]
class ApprovalAssigneeResolverTest extends TestCase
{
    public function testKeepsConfiguredOrderWithoutDuplicates(): void
    {
        $resolver = $this->source(['second', 'first', 'second']);
        [$instance, $node, $context] = $this->inputs([]);

        $result = (new ApprovalAssigneeResolver(new AssigneeResolverRegistry([$resolver])))->resolve($instance, $node, $context);

        static::assertSame(['second', 'first'], $result->assigneeIds);
        static::assertNull($result->automaticDecision);
    }

    #[DataProvider('automaticActions')]
    public function testOnlyExplicitPoliciesMakeAutomaticDecisions(string $action): void
    {
        [$instance, $node, $context] = $this->inputs(['action' => $action]);
        $resolver = $this->source([]);
        $resolver->method('resolve')->willThrowException(WorkflowExecutionException::noAssignee('approval', 'dynamic'));

        $result = (new ApprovalAssigneeResolver(new AssigneeResolverRegistry([$resolver])))->resolve($instance, $node, $context);

        static::assertSame([], $result->assigneeIds);
        static::assertSame($action, $result->automaticDecision);
    }

    public static function automaticActions(): iterable
    {
        yield 'explicit automatic approval' => ['approve'];
        yield 'explicit automatic rejection' => ['reject'];
    }

    public function testDefaultPolicyBlocksEmptyAssignments(): void
    {
        [$instance, $node, $context] = $this->inputs([]);
        $resolver = new ApprovalAssigneeResolver(new AssigneeResolverRegistry([$this->source([])]));

        $this->expectExceptionObject(WorkflowExecutionException::noAssignee('approval', 'dynamic'));
        $resolver->resolve($instance, $node, $context);
    }

    public function testInvalidGraphCannotBecomeAutomaticApproval(): void
    {
        [$instance, $node, $context] = $this->inputs(['action' => 'approve']);
        $source = $this->source([]);
        $source->method('resolve')->willThrowException(WorkflowExecutionException::invalidGraph('Invalid captured department.'));

        $this->expectExceptionObject(WorkflowExecutionException::invalidGraph('Invalid captured department.'));
        (new ApprovalAssigneeResolver(new AssigneeResolverRegistry([$source])))->resolve($instance, $node, $context);
    }

    public function testReplacementRunsThroughTheInstalledUserResolver(): void
    {
        $replacement = '0123456789abcdef0123456789abcdef';
        [$instance, $node, $context] = $this->inputs(['action' => 'assign', 'userId' => $replacement]);
        $users = static::createMock(AssigneeResolverInterface::class);
        $users->method('getAssigneeType')->willReturn('user');
        $users->expects($this->once())->method('resolve')->willReturnCallback(static function ($instance, GraphNode $node) use ($replacement): array {
            static::assertSame([$replacement], $node->property('assigneeValue'));

            return [$replacement];
        });
        $resolver = new ApprovalAssigneeResolver(new AssigneeResolverRegistry([$this->source([]), $users]));

        static::assertSame([$replacement], $resolver->resolve($instance, $node, $context)->assigneeIds);
    }

    /**
     * @param list<string> $ids
     */
    private function source(array $ids): AssigneeResolverInterface&Stub
    {
        $source = static::createStub(AssigneeResolverInterface::class);
        $source->method('getAssigneeType')->willReturn('dynamic');
        $source->method('resolve')->willReturn($ids);

        return $source;
    }

    /**
     * @param array<string, mixed> $policy
     *
     * @return array{WorkflowInstanceEntity, GraphNode, WorkflowExecutionContext}
     */
    private function inputs(array $policy): array
    {
        $instance = new WorkflowInstanceEntity();
        $node = new GraphNode('approval', 'task', 0, 0, ['assigneeType' => 'dynamic', 'missingAssignee' => $policy]);
        $context = new WorkflowExecutionContext(GraphData::fromArray(['nodes' => [], 'edges' => []]), $instance, Context::createDefaultContext(), null, 1);

        return [$instance, $node, $context];
    }
}
