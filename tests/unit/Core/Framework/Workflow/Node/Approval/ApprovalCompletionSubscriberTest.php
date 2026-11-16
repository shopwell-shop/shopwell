<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowCompletingEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogCollection;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalCompletionSubscriber;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStateHandler;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalCompletionSubscriber::class)]
class ApprovalCompletionSubscriberTest extends TestCase
{
    #[DataProvider('completionContracts')]
    public function testOnlyMarkedApprovalStagesOwnApprovalCompletion(bool $marked, string $result, ?string $expectedAction): void
    {
        $tasks = StaticEntityRepository::of(WorkflowTaskCollection::class, [new WorkflowTaskCollection()]);
        $stateMachines = $this->createMock(StateMachineRegistry::class);
        $stateMachines->expects($expectedAction === null ? $this->never() : $this->once())
            ->method('transition')
            ->with(static::callback(static fn (Transition $transition): bool => $transition->getTransitionName() === $expectedAction), static::anything())
            ->willReturn(new StateMachineStateCollection());
        $subscriber = new ApprovalCompletionSubscriber(
            new WorkflowTaskFinder($tasks, StaticEntityRepository::of(WorkflowTaskHistoryCollection::class, []), StaticEntityRepository::of(WorkflowLogCollection::class, [])),
            new WorkflowTaskStateHandler($stateMachines, $tasks, new MockClock()),
            new WorkflowInstanceStateHandler($stateMachines, StaticEntityRepository::of(WorkflowInstanceCollection::class, []), new MockClock()),
        );
        $graph = GraphData::fromArray(['nodes' => [[
            'id' => 'task', 'type' => 'snaker:task', 'properties' => ['approvalStage' => $marked],
        ]], 'edges' => []]);
        $instance = new WorkflowInstanceEntity();
        $instance->id = 'instance';
        $event = new WorkflowCompletingEvent($result, new WorkflowExecutionContext($graph, $instance, Context::createDefaultContext(), null, 1));

        $subscriber->complete($event);

        static::assertSame($expectedAction !== null, $event->handled);
        static::assertCount($expectedAction === null ? 0 : 1, $tasks->searchedCriteria);
    }

    /**
     * @return iterable<string, array{bool, string, string|null}>
     */
    public static function completionContracts(): iterable
    {
        yield 'generic task retains custom result' => [false, 'custom-result', null];
        yield 'generic task retains rejection semantics' => [false, 'rejected', null];
        yield 'approval stage agrees' => [true, 'approved', 'agree'];
        yield 'approval stage rejects' => [true, 'rejected', 'reject'];
    }
}
