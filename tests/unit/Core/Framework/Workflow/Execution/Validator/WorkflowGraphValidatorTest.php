<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\EventListener\ErrorResponseFactory;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverInterface;
use Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ExprNormalizer;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\BranchMergeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\CycleRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\EdgeConditionRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\GraphRuleInterface;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\NodePropertiesRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ReachabilityRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\ShapeRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleEndRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Rule\SingleStartRule;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * Assert stable validation codes used by the editor to locate graph problems.
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
        // Keep rule order consistent with services.php so both validators report problems in the same order.
        $normalizer = new ExprNormalizer();

        $this->validator = new WorkflowGraphValidator([
            new ShapeRule(),
            new SingleStartRule(),
            new SingleEndRule(),
            new CycleRule(),
            new ReachabilityRule(),
            new NodePropertiesRule(new AssigneeResolverRegistry(array_map(function (string $type): AssigneeResolverInterface {
                $resolver = $this->createStub(AssigneeResolverInterface::class);
                $resolver->method('getAssigneeType')->willReturn($type);

                return $resolver;
            }, ['user', 'role', 'initiator'])), ApprovalRegistryFixture::modes()),
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

    #[DataProvider('unexpectedRuleFailures')]
    public function testUnexpectedRuleFailureHasSafeResponseAndPreservesItsCause(\Throwable $failure): void
    {
        $rule = static::createStub(GraphRuleInterface::class);
        $rule->method('validate')->willThrowException($failure);
        $validator = new WorkflowGraphValidator([$rule]);
        $thrown = null;

        try {
            $validator->validate(GraphData::fromArray($this->happyPath()), new ValidationContext());
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        static::assertNotNull($thrown);
        $response = (new ErrorResponseFactory())->getResponseFromException($thrown, debug: false);
        static::assertSame(500, $response->getStatusCode());
        static::assertStringNotContainsString('internal connection details', (string) $response->getContent());
        static::assertInstanceOf(WorkflowExecutionException::class, $thrown);
        static::assertSame('FRAMEWORK__WORKFLOW_EXECUTION_VALIDATION_FAILED', $thrown->getErrorCode());
        static::assertSame('Workflow validation failed.', $thrown->getMessage());
        static::assertSame($failure, $thrown->getPrevious());
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function unexpectedRuleFailures(): iterable
    {
        yield 'infrastructure exception' => [new \RuntimeException('internal connection details')];
        yield 'programming error' => [new \Error('internal connection details')];
    }

    public function testBusinessViolationsFromAllRulesAreAggregated(): void
    {
        $first = Violation::error('FIRST', 'First user correction');
        $second = Violation::error('SECOND', 'Second user correction');
        $firstRule = static::createStub(GraphRuleInterface::class);
        $firstRule->method('validate')->willReturn([$first]);
        $secondRule = static::createStub(GraphRuleInterface::class);
        $secondRule->method('validate')->willReturn([$second]);

        static::assertSame([$first, $second], (new WorkflowGraphValidator([$firstRule, $secondRule]))->validate(GraphData::fromArray($this->happyPath()), new ValidationContext()));
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
        // Add a back edge between approval nodes to create a cycle.
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
     * Branches that only meet at the end produce a warning.
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

        // Warnings do not prevent saving.
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
     * A linear graph avoids branch-merge warnings; dedicated cases cover branching.
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
