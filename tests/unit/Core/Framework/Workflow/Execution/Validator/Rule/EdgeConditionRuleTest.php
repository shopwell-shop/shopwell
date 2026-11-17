<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Validator\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\EdgeConditionRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EdgeConditionRule::class)]
class EdgeConditionRuleTest extends TestCase
{
    public function testValidatesStructuredConditionAgainstPublishedFieldTypes(): void
    {
        $schema = [['key' => 'amount', 'label' => 'Amount', 'type' => 'amount']];
        $expression = (new ApprovalCondition())->encode(['field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000'], $schema);
        $graph = GraphData::fromArray(['nodes' => [], 'edges' => [['id' => 'branch', 'sourceNodeId' => 'condition', 'targetNodeId' => 'finance', 'properties' => ['expr' => $expression]]]]);
        $rule = new EdgeConditionRule(new ExprNormalizer());
        static::assertSame([], $rule->validate($graph, ValidationContext::fromFormSchema($schema)));
        static::assertCount(1, $rule->validate($graph, new ValidationContext(['amount'], ['amount' => 'text'])));
        static::assertCount(1, $rule->validate($graph, ValidationContext::empty()));
    }

    public function testValidatesEveryFieldInNestedGroups(): void
    {
        $schema = [
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
            ['key' => 'reason', 'label' => 'Reason', 'type' => 'text'],
        ];
        $expression = (new ApprovalCondition())->encode(['operator' => 'or', 'conditions' => [
            ['field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000'],
            ['operator' => 'and', 'conditions' => [
                ['field' => 'request.reason', 'fieldType' => 'text', 'operator' => 'equals', 'value' => 'Travel'],
            ]],
        ]], $schema);
        $graph = GraphData::fromArray(['nodes' => [], 'edges' => [
            ['id' => 'route', 'sourceNodeId' => 'condition', 'targetNodeId' => 'approval', 'properties' => ['expr' => $expression]],
        ]]);
        $rule = new EdgeConditionRule(new ExprNormalizer());
        static::assertSame([], $rule->validate($graph, ValidationContext::fromFormSchema($schema)));
        static::assertCount(1, $rule->validate($graph, ValidationContext::fromFormSchema([$schema[0]])));
    }
}
