<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignResult;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalVotes;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Tests\Unit\Core\Framework\Workflow\_fixtures\ApprovalRegistryFixture;

/**
 * Specify approval-mode differences, including the requirement that every assignee reject an or-sign node.
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
        $this->calculator = new CounterSignCalculator(ApprovalRegistryFixture::modes());
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
     * Sequential approval uses the full snapshot size because later tasks do not exist yet.
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
     * Canceled tasks must not contribute to the approval denominator.
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

    public function testPluginReceivesThePinnedConfigurationAndCountedVotes(): void
    {
        $mode = static::createStub(ApprovalMode::class);
        $mode->method('getName')->willReturn('example:quorum');
        $mode->method('getType')->willReturn('example:quorum');
        $mode->method('getVersion')->willReturn(2);
        $mode->method('resolve')->willReturnCallback(static function (ApprovalVotes $votes, array $configuration): CounterSignResult {
            if ($votes->agreed >= $configuration['required']) {
                return CounterSignResult::agreed($votes->agreed, $votes->rejected, $votes->total);
            }

            return CounterSignResult::pending($votes->agreed, $votes->rejected, $votes->total);
        });
        $calculator = new CounterSignCalculator(ApprovalRegistryFixture::modes([$mode]));
        $tasks = $this->tasks([WorkflowTaskStates::AGREED, WorkflowTaskStates::AGREED, WorkflowTaskStates::TODO, WorkflowTaskStates::CANCELED]);

        $result = $calculator->resolve($tasks, 'example:quorum', null, ['required' => 2], 2);

        static::assertTrue($result->isAgreed());
        static::assertSame(3, $result->total());
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

        // EntityCollection requires a unique identifier when indexing entities.
        // "must not be accessed before initialization"
        $task->setUniqueIdentifier($task->id);

        return $task;
    }
}
