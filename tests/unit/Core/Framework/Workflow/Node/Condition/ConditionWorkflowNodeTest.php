<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Node\WorkflowNode;
use Shopwell\Core\Framework\Workflow\Definition\WorkflowDocument;
use Shopwell\Core\Framework\Workflow\Event\WorkflowNodePortsEvent;
use Shopwell\Core\Framework\Workflow\Event\WorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\Node\Condition\ConditionWorkflowNodeLoader;
use Shopwell\Core\Framework\Workflow\Node\Condition\ConditionWorkflowSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConditionWorkflowSubscriber::class)]
class ConditionWorkflowNodeTest extends TestCase
{
    public function testResolvesOrderedBranchesAndFallbackWithoutChangingCoreTypes(): void
    {
        $node = new WorkflowNode('route', 'core:condition', 1, ['branches' => [
            ['id' => 'branch-b', 'label' => 'Paid', 'field' => 'order.status', 'operator' => 'equals', 'value' => 'paid'],
            ['id' => 'branch-a', 'label' => 'No status', 'field' => 'order.status', 'operator' => 'isEmpty', 'value' => ''],
        ]]);
        $type = iterator_to_array((new ConditionWorkflowNodeLoader())->load())[0];
        $subscriber = new ConditionWorkflowSubscriber();
        $ports = new WorkflowNodePortsEvent($node, $type);
        $subscriber->resolvePorts($ports);
        static::assertSame(['branch-b', 'branch-a', 'fallback'], $ports->getOutputPorts());
        $validation = new WorkflowValidationEvent(new WorkflowDocument([$node], []));
        $subscriber->validate($validation);
        static::assertSame([], $validation->getViolations());
    }

    #[DataProvider('comparisonOperators')]
    public function testAcceptsTypedComparisonDefinitionsWithoutExecutingThem(string $operator): void
    {
        $node = new WorkflowNode('route', 'core:condition', 1, ['branches' => [
            ['id' => 'branch-large', 'label' => 'Finance approval', 'field' => 'request.amount', 'operator' => $operator, 'value' => '5000.00'],
        ]]);
        $event = new WorkflowValidationEvent(new WorkflowDocument([$node], []));

        (new ConditionWorkflowSubscriber())->validate($event);

        static::assertSame([], $event->getViolations());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function comparisonOperators(): iterable
    {
        yield 'strictly larger expense' => ['greaterThan'];
        yield 'inclusive lower threshold' => ['greaterThanOrEqual'];
        yield 'strictly smaller expense' => ['lessThan'];
        yield 'inclusive upper threshold' => ['lessThanOrEqual'];
    }

    public function testAcceptsNestedConditionsAndRejectsInvalidChildren(): void
    {
        $comparison = ['field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000'];
        $group = ['operator' => 'and', 'conditions' => [$comparison, ['operator' => 'or', 'conditions' => [$comparison]]]];
        $node = new WorkflowNode('route', 'core:condition', 1, ['branches' => [
            ['id' => 'branch-a', 'label' => 'Finance'] + $group,
        ]]);
        $event = new WorkflowValidationEvent(new WorkflowDocument([$node], []));
        (new ConditionWorkflowSubscriber())->validate($event);
        static::assertSame([], $event->getViolations());

        $group['conditions'][1]['conditions'] = [];
        $invalid = new WorkflowNode('route', 'core:condition', 1, ['branches' => [
            ['id' => 'branch-a', 'label' => 'Finance'] + $group,
        ]]);
        $event = new WorkflowValidationEvent(new WorkflowDocument([$invalid], []));
        (new ConditionWorkflowSubscriber())->validate($event);
        static::assertSame(['condition_invalid_rule'], array_column($event->getViolations(), 'code'));
    }

    public function testIncompleteRulesKeepTheirPortsAndInvalidIdentifiersAreFiltered(): void
    {
        $node = new WorkflowNode('route', 'core:condition', 1, ['branches' => [
            ['id' => 'branch-a'], ['id' => 'branch-a'], ['id' => 'fallback'], ['id' => 'invalid'],
        ]]);
        $type = iterator_to_array((new ConditionWorkflowNodeLoader())->load())[0];
        $event = new WorkflowNodePortsEvent($node, $type);
        (new ConditionWorkflowSubscriber())->resolvePorts($event);
        static::assertSame(['branch-a', 'fallback'], $event->getOutputPorts());
    }

    public function testReportsInvalidConditionsAndDuplicatePorts(): void
    {
        $node = new WorkflowNode('route', 'core:condition', 1, ['branches' => [
            ['id' => 'branch-a', 'label' => '', 'field' => '', 'operator' => 'invalid', 'value' => null],
            ['id' => 'branch-a', 'label' => 'Duplicate', 'field' => 'status', 'operator' => 'equals', 'value' => 'ok'],
        ]]);
        $event = new WorkflowValidationEvent(new WorkflowDocument([$node], []));
        (new ConditionWorkflowSubscriber())->validate($event);
        static::assertSame(['condition_invalid_rule', 'condition_invalid_branches'], array_column($event->getViolations(), 'code'));
        static::assertSame('route', $event->getViolations()[0]->nodeId);
    }

    public function testReportsMissingBranchesAndIgnoresOtherNodeTypes(): void
    {
        $event = new WorkflowValidationEvent(new WorkflowDocument([
            new WorkflowNode('route', 'core:condition', 1),
            new WorkflowNode('custom', 'example:condition', 1),
        ], []));
        (new ConditionWorkflowSubscriber())->validate($event);
        static::assertSame(['condition_invalid_branches'], array_column($event->getViolations(), 'code'));
    }
}
