<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Validator\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\NodePropertiesRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodePropertiesRule::class)]
class NodePropertiesRuleTest extends TestCase
{
    #[DataProvider('organizationTypes')]
    public function testUnavailableResolverIsRejectedBeforeExecution(string $type): void
    {
        $rule = new NodePropertiesRule(new AssigneeResolverRegistry([]), ApprovalRegistryFixture::modes());
        $violations = $rule->validate($this->graph($type), new ValidationContext());

        static::assertCount(1, $violations);
        static::assertSame('review', $violations[0]->nodeId);
        static::assertStringContainsString($type, $violations[0]->message);
    }

    #[DataProvider('organizationTypes')]
    public function testInstalledResolverCanValidateAndResolveTheNode(string $type): void
    {
        $resolver = static::createStub(AssigneeResolverInterface::class);
        $resolver->method('getAssigneeType')->willReturn($type);
        $resolver->method('resolve')->willReturn(['manager']);
        $registry = new AssigneeResolverRegistry([$resolver]);
        $graph = $this->graph($type);

        static::assertSame([], (new NodePropertiesRule($registry, ApprovalRegistryFixture::modes()))->validate($graph, new ValidationContext()));
        $instance = new WorkflowInstanceEntity();
        $execution = new WorkflowExecutionContext($graph, $instance, Context::createDefaultContext(), null, 1);
        static::assertSame(['manager'], $registry->for($type)->resolve($instance, $graph->nodeList()[0], $execution));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function organizationTypes(): iterable
    {
        yield 'initiator manager needs its extension' => ['leader'];
        yield 'unit manager needs its extension' => ['unitLeader'];
    }

    private function graph(string $type): GraphData
    {
        return GraphData::fromArray(['nodes' => [[
            'id' => 'review', 'type' => 'snaker:task',
            'properties' => ['name' => 'Review', 'countersignType' => 'single', 'assigneeType' => $type],
        ]], 'edges' => []]);
    }
}
