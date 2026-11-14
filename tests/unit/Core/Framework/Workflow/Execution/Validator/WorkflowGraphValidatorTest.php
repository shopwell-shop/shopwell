<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\BranchMergeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\CycleRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\EdgeConditionRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\NodePropertiesRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ReachabilityRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleEndRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleStartRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;

/**
 * 校验链。
 *
 * 断言的是**错误码**而不是文案：前端按码定位节点，文案随时会改，
 * 码一改就是破坏性变更（前后端要同时发版）。
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowGraphValidator::class)]
class WorkflowGraphValidatorTest extends TestCase
{
    private WorkflowGraphValidator $validator;

    protected function setUp(): void
    {
        // 顺序必须与 services.php 里的注册顺序一致，
        // 否则同一个图两端报出来的第一条问题不同
        $normalizer = new ExprNormalizer();

        $this->validator = new WorkflowGraphValidator([
            new ShapeRule(),
            new SingleStartRule(),
            new SingleEndRule(),
            new CycleRule(),
            new ReachabilityRule(),
            new NodePropertiesRule(),
            new EdgeConditionRule($normalizer),
            new BranchMergeRule(),
        ]);
    }

    public function testHappyPathHasNoErrors(): void
    {
        $violations = $this->validator->validate(
            GraphData::fromArray($this->happyPath()),
            new ValidationContext(['amount'])
        );

        static::assertSame([], $violations);
    }

    public function testMissingEndNodeIsReported(): void
    {
        $graph = $this->happyPath();
        $graph['nodes'] = array_values(array_filter(
            $graph['nodes'],
            static fn (array $node): bool => $node['type'] !== 'snaker:end'
        ));

        static::assertContains('WORKFLOW_EXECUTION_003', $this->codes(GraphData::fromArray($graph)));
    }

    public function testCycleIsReported(): void
    {
        $graph = $this->happyPath();
        // 追加一条审批 → 审批 的回边，制造环
        $graph['edges'][] = $this->edge('e-cycle', 'task-1', 'task-1');

        static::assertContains('WORKFLOW_EXECUTION_004', $this->codes(GraphData::fromArray($graph)));
    }

    public function testUnreachableNodeIsReported(): void
    {
        $graph = $this->happyPath();
        $graph['nodes'][] = $this->node('orphan', 'snaker:task', [
            'name' => '孤立节点',
            'countersignType' => 'single',
            'assigneeType' => 'initiator',
        ]);

        static::assertContains('WORKFLOW_EXECUTION_005', $this->codes(GraphData::fromArray($graph)));
    }

    public function testTaskWithoutAssigneeIsReported(): void
    {
        $graph = $this->happyPath();
        $graph['nodes'][1]['properties']['assigneeValue'] = [];

        static::assertContains('WORKFLOW_EXECUTION_006', $this->codes(GraphData::fromArray($graph)));
    }

    public function testConditionOnUnknownFieldIsReported(): void
    {
        $graph = $this->happyPath();
        $graph['edges'][1]['properties']['expr'] = '${form.notInSchema} > 1';

        static::assertContains('WORKFLOW_EXECUTION_007', $this->codes(GraphData::fromArray($graph)));
    }

    /**
     * 两条分支各自走到结束：只汇合在结束节点上不算汇聚，给一条警告。
     */
    public function testBranchWithoutMergeIsOnlyAWarning(): void
    {
        $graph = [
            'nodes' => [
                $this->node('start', 'snaker:start', ['name' => '发起人']),
                $this->node('task-1', 'snaker:task', [
                    'name' => '判断',
                    'countersignType' => 'single',
                    'assigneeType' => 'initiator',
                ]),
                $this->node('task-2', 'snaker:task', [
                    'name' => '大额审批',
                    'countersignType' => 'single',
                    'assigneeType' => 'initiator',
                ]),
                $this->node('task-3', 'snaker:task', [
                    'name' => '小额审批',
                    'countersignType' => 'single',
                    'assigneeType' => 'initiator',
                ]),
                $this->node('end', 'snaker:end', []),
            ],
            'edges' => [
                $this->edge('e-1', 'start', 'task-1'),
                $this->edge('e-2', 'task-1', 'task-2', '${form.amount} > 1000'),
                $this->edge('e-3', 'task-1', 'task-3'),
                $this->edge('e-4', 'task-2', 'end'),
                $this->edge('e-5', 'task-3', 'end'),
            ],
        ];

        $violations = $this->validator->validate(GraphData::fromArray($graph), new ValidationContext(['amount']));

        $codes = array_map(static fn (Violation $v): string => $v->code, $violations);
        static::assertContains('WORKFLOW_EXECUTION_101', $codes);

        // 警告不拦截保存
        static::assertFalse($this->validator->hasErrors($violations));
    }

    /**
     * @return list<string>
     */
    private function codes(GraphData $graph): array
    {
        return array_map(
            static fn (Violation $violation): string => $violation->code,
            $this->validator->validate($graph, new ValidationContext(['amount']))
        );
    }

    /**
     * 开始 → 主管审批 → 财务审批 → 结束（单线，无条件分支）。
     *
     * 单线是刻意选的：分支不汇聚会触发 `WORKFLOW_EXECUTION_101` 警告，
     * 让"正常图零违规"这条断言变得含糊。分支行为由专门用例覆盖。
     *
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    private function happyPath(): array
    {
        return [
            'nodes' => [
                $this->node('start', 'snaker:start', ['name' => '发起人']),
                $this->node('task-1', 'snaker:task', [
                    'name' => '主管审批',
                    'countersignType' => 'single',
                    'assigneeType' => 'user',
                    'assigneeValue' => ['user-a'],
                ]),
                $this->node('task-2', 'snaker:task', [
                    'name' => '财务审批',
                    'countersignType' => 'countersign',
                    'assigneeType' => 'role',
                    'assigneeValue' => ['role-finance'],
                ]),
                $this->node('end', 'snaker:end', []),
            ],
            'edges' => [
                $this->edge('e-1', 'start', 'task-1'),
                $this->edge('e-2', 'task-1', 'task-2'),
                $this->edge('e-3', 'task-2', 'end'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function node(string $id, string $type, array $properties): array
    {
        return ['id' => $id, 'type' => $type, 'x' => 0, 'y' => 0, 'properties' => $properties];
    }

    /**
     * @return array<string, mixed>
     */
    private function edge(string $id, string $source, string $target, ?string $expr = null): array
    {
        return [
            'id' => $id,
            'sourceNodeId' => $source,
            'targetNodeId' => $target,
            'properties' => $expr === null ? [] : ['expr' => $expr],
        ];
    }
}
