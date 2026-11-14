<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * 会签判定表。
 *
 * 这些用例是**规格**，不是"补覆盖率"：四种审批方式的行为差异全在这里，
 * 改 {@see CounterSignCalculator} 之前先看它们是否还成立。
 *
 * 特别注意 orSign 的两条：任一同意 → 通过；**全部**拒绝 → 拒绝。
 * 写成"任一拒绝即拒绝"是最常见的实现错误，而且测试里只要不覆盖"部分拒绝"
 * 就发现不了。
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CounterSignCalculator::class)]
class CounterSignCalculatorTest extends TestCase
{
    private CounterSignCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CounterSignCalculator();
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function singleProvider(): iterable
    {
        yield '同意了就通过' => [[WorkflowTaskStates::AGREED], 'agreed'];
        yield '拒绝了就拒绝' => [[WorkflowTaskStates::REJECTED], 'rejected'];
        yield '还没处理就是待办' => [[WorkflowTaskStates::TODO], 'pending'];
    }

    /**
     * @param list<string> $states
     */
    #[DataProvider('singleProvider')]
    public function testSingle(array $states, string $expected): void
    {
        $result = $this->calculator->resolve($this->tasks($states), WorkflowCountersignTypes::SINGLE);

        static::assertSame($expected, $result->status());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function orSignProvider(): iterable
    {
        yield '一人同意即通过' => [[WorkflowTaskStates::AGREED, WorkflowTaskStates::TODO, WorkflowTaskStates::TODO], 'agreed'];
        yield '部分拒绝但另一人同意，仍然通过' => [[WorkflowTaskStates::REJECTED, WorkflowTaskStates::AGREED], 'agreed'];
        yield '全部拒绝才拒绝' => [[WorkflowTaskStates::REJECTED, WorkflowTaskStates::REJECTED], 'rejected'];
        yield '一人拒绝一人待办，继续等' => [[WorkflowTaskStates::REJECTED, WorkflowTaskStates::TODO], 'pending'];
    }

    /**
     * @param list<string> $states
     */
    #[DataProvider('orSignProvider')]
    public function testOrSign(array $states, string $expected): void
    {
        $result = $this->calculator->resolve($this->tasks($states), WorkflowCountersignTypes::OR_SIGN);

        static::assertSame($expected, $result->status());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function firstDecisionProvider(): iterable
    {
        yield 'first approval closes the node' => [[WorkflowTaskStates::TODO, WorkflowTaskStates::AGREED, WorkflowTaskStates::TODO], 'agreed'];
        yield 'first rejection closes the node' => [[WorkflowTaskStates::TODO, WorkflowTaskStates::REJECTED, WorkflowTaskStates::TODO], 'rejected'];
        yield 'no decision keeps the node waiting' => [[WorkflowTaskStates::TODO, WorkflowTaskStates::TODO], 'pending'];
    }

    /**
     * @param list<string> $states
     */
    #[DataProvider('firstDecisionProvider')]
    public function testFirstDecision(array $states, string $expected): void
    {
        $result = $this->calculator->resolve($this->tasks($states), WorkflowCountersignTypes::FIRST_DECISION);

        static::assertSame($expected, $result->status());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function countersignProvider(): iterable
    {
        yield '全部同意才通过' => [[WorkflowTaskStates::AGREED, WorkflowTaskStates::AGREED], 'agreed'];
        yield '一票同意一票待办，继续等' => [[WorkflowTaskStates::AGREED, WorkflowTaskStates::TODO], 'pending'];
        yield '任一拒绝即拒绝' => [[WorkflowTaskStates::AGREED, WorkflowTaskStates::REJECTED, WorkflowTaskStates::TODO], 'rejected'];
    }

    /**
     * @param list<string> $states
     */
    #[DataProvider('countersignProvider')]
    public function testCountersign(array $states, string $expected): void
    {
        $result = $this->calculator->resolve($this->tasks($states), WorkflowCountersignTypes::COUNTERSIGN);

        static::assertSame($expected, $result->status());
    }

    /**
     * 顺序会签：分母必须来自快照，而不是"现有任务数"。
     *
     * 这条用例防的是最隐蔽的一个 bug：任务逐条创建，第一人同意时
     * 现有任务数 = 1、已同意 = 1，若拿现有任务数当分母就会立刻通过。
     */
    public function testOrderSignWaitsForEveryoneInTheSnapshot(): void
    {
        $tasks = new WorkflowTaskCollection([$this->task(1, WorkflowTaskStates::AGREED)]);

        $result = $this->calculator->resolve($tasks, WorkflowCountersignTypes::ORDER_SIGN, 3);

        static::assertSame('pending', $result->status());
    }

    public function testOrderSignAgreesWhenTheLastOneAgrees(): void
    {
        $tasks = new WorkflowTaskCollection([
            $this->task(1, WorkflowTaskStates::AGREED),
            $this->task(2, WorkflowTaskStates::AGREED),
            $this->task(3, WorkflowTaskStates::AGREED),
        ]);

        $result = $this->calculator->resolve($tasks, WorkflowCountersignTypes::ORDER_SIGN, 3);

        static::assertSame('agreed', $result->status());
    }

    public function testOrderSignRejectsOnAnyRejection(): void
    {
        $tasks = new WorkflowTaskCollection([
            $this->task(1, WorkflowTaskStates::AGREED),
            $this->task(2, WorkflowTaskStates::REJECTED),
        ]);

        $result = $this->calculator->resolve($tasks, WorkflowCountersignTypes::ORDER_SIGN, 3);

        static::assertSame('rejected', $result->status());
    }

    /**
     * 取消的任务不能算进分母：否则 3 人会签里有 1 人被撤回后，
     * 剩下 2 人全部同意也凑不齐 3 票，流程永久卡死。
     */
    public function testCanceledTasksAreNotCountedInTheDenominator(): void
    {
        $tasks = new WorkflowTaskCollection([
            $this->task(1, WorkflowTaskStates::AGREED),
            $this->task(2, WorkflowTaskStates::AGREED),
            $this->task(3, WorkflowTaskStates::CANCELED),
        ]);

        $result = $this->calculator->resolve($tasks, WorkflowCountersignTypes::COUNTERSIGN);

        static::assertSame('agreed', $result->status());
        static::assertSame(2, $result->total());
    }

    /**
     * @param list<string> $states
     */
    private function tasks(array $states): WorkflowTaskCollection
    {
        $tasks = [];
        $sequence = 1;

        foreach ($states as $state) {
            $tasks[] = $this->task($sequence++, $state);
        }

        return new WorkflowTaskCollection($tasks);
    }

    private function task(int $sequence, string $state): WorkflowTaskEntity
    {
        $task = new WorkflowTaskEntity();
        $task->id = \sprintf('task-%d', $sequence);
        $task->instanceId = 'instance';
        $task->nodeId = 'node';
        $task->nodeName = '审批';
        $task->countersignType = WorkflowCountersignTypes::COUNTERSIGN;
        $task->round = 1;
        $task->sequence = $sequence;
        $task->assigneeId = \sprintf('user-%d', $sequence);
        $task->assigneeType = 'user';
        $task->stateId = Uuid::randomHex();
        $task->stateMachineState = new StateMachineStateEntity();
        $task->stateMachineState->setId($task->stateId);
        $task->stateMachineState->setTechnicalName($state);

        // EntityCollection 构造时按 unique identifier 建索引，不设会抛
        // "must not be accessed before initialization"
        $task->setUniqueIdentifier($task->id);

        return $task;
    }
}
