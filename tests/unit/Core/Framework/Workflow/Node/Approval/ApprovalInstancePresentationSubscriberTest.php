<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresentationEvent;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowTaskPresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Condition\ConditionEvaluatorInterface;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalInstancePresentationSubscriber;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalRoute;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskHistoryCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalInstancePresentationSubscriber::class)]
class ApprovalInstancePresentationSubscriberTest extends TestCase
{
    public function testGenericDetailDoesNotLoadApprovalData(): void
    {
        $instance = new WorkflowInstanceEntity();
        $instance->graphSnapshot = ['nodes' => [['id' => 'wait', 'type' => 'example:wait']], 'edges' => []];
        $event = new WorkflowInstancePresentationEvent($instance, Context::createDefaultContext(), ['id' => 'generic'], withDetail: true);

        $this->subscriber()->present($event);

        static::assertSame(['id' => 'generic'], $event->getData());
    }

    public function testSummaryDoesNotInspectTheGraphOrLoadApprovalData(): void
    {
        $event = new WorkflowInstancePresentationEvent(new WorkflowInstanceEntity(), Context::createDefaultContext(), ['id' => 'summary'], withDetail: false);

        $this->subscriber()->present($event);

        static::assertSame(['id' => 'summary'], $event->getData());
    }

    /**
     * @param list<string> $taskStates
     */
    #[DataProvider('stageStates')]
    public function testApprovalProjectionRetainsActualPathAndClassifiesStage(string $instanceState, ?string $currentNode, string $countersignType, array $taskStates, string $expectedStatus): void
    {
        $instance = new WorkflowInstanceEntity();
        $instance->id = 'instance';
        $instance->currentNodeId = $currentNode;
        $state = new StateMachineStateEntity();
        $state->setTechnicalName($instanceState);
        $instance->stateMachineState = $state;
        $instance->graphSnapshot = ['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start'],
            ['id' => 'review', 'type' => 'snaker:task', 'properties' => [
                'approvalStage' => true, 'name' => 'Review', 'assigneeValue' => ['reviewer'], 'countersignType' => $countersignType,
            ]],
            ['id' => 'end', 'type' => 'snaker:end'],
        ], 'edges' => [
            ['id' => 'start-review', 'sourceNodeId' => 'start', 'targetNodeId' => 'review'],
            ['id' => 'review-end', 'sourceNodeId' => 'review', 'targetNodeId' => 'end'],
        ]];
        $task = new WorkflowTaskEntity();
        $task->id = 'task';
        $task->setUniqueIdentifier($task->id);
        $task->instanceId = $instance->id;
        $task->nodeId = 'review';
        $task->nodeName = 'Review';
        $task->countersignType = $countersignType;
        $task->assigneeId = 'reviewer';
        $task->assigneeType = 'user';
        $taskEntities = [];

        foreach ($taskStates as $index => $taskState) {
            $stageTask = clone $task;
            $stageTask->id = 'task-' . $index;
            $stageTask->setUniqueIdentifier($stageTask->id);
            $taskStateEntity = new StateMachineStateEntity();
            $taskStateEntity->setTechnicalName($taskState);
            $stageTask->stateMachineState = $taskStateEntity;
            $taskEntities[] = $stageTask;
        }

        $actualPath = ['nodeIds' => ['start'], 'edgeIds' => []];
        $event = new WorkflowInstancePresentationEvent($instance, Context::createDefaultContext(), ['selectedPath' => $actualPath], withDetail: true);
        $tasks = new StaticEntityRepository([new WorkflowTaskCollection($taskEntities), new WorkflowTaskCollection($taskEntities)]);
        $subscriber = $this->subscriber($tasks);
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($subscriber);

        $dispatcher->dispatch($event);

        $data = $event->getData();
        static::assertSame($actualPath, $data['selectedPath']);
        static::assertSame(['nodeIds' => ['start', 'review', 'end'], 'edgeIds' => ['start-review', 'review-end']], $data['plannedPath']);
        static::assertSame($expectedStatus, $data['approvalStages'][0]['status']);
        static::assertSame([['id' => 'reviewer', 'name' => 'reviewer']], $data['approvalStages'][0]['participants']);
        static::assertSame('task-0', $data['approvalTasks'][0]['id']);
        static::assertSame([], $data['history']);
        static::assertSame([], $data['copies']);
        static::assertCount($currentNode === null ? 0 : \count($taskStates), $data['tasks']);
    }

    /**
     * @return iterable<string, array{string, ?string, string, list<string>, string}>
     */
    public static function stageStates(): iterable
    {
        yield 'current pending task is active' => [WorkflowInstanceStates::RUNNING, 'review', 'single', [WorkflowTaskStates::TODO], 'active'];
        yield 'rejected task takes priority over current stage' => [WorkflowInstanceStates::RUNNING, 'review', 'single', [WorkflowTaskStates::REJECTED], 'rejected'];
        yield 'agreed stage is approved' => [WorkflowInstanceStates::RUNNING, 'end', 'single', [WorkflowTaskStates::AGREED], 'approved'];
        yield 'countersign with all agreed is approved' => [WorkflowInstanceStates::RUNNING, 'end', 'countersign', [WorkflowTaskStates::AGREED], 'approved'];
        yield 'single approval allows canceled siblings' => [WorkflowInstanceStates::RUNNING, 'end', 'single', [WorkflowTaskStates::AGREED, WorkflowTaskStates::CANCELED], 'approved'];
        yield 'countersign requires every participant to agree' => [WorkflowInstanceStates::RUNNING, 'end', 'countersign', [WorkflowTaskStates::AGREED, WorkflowTaskStates::CANCELED], 'pending'];
        yield 'an outstanding sibling prevents approval' => [WorkflowInstanceStates::RUNNING, 'end', 'single', [WorkflowTaskStates::AGREED, WorkflowTaskStates::TODO], 'pending'];
        yield 'unreached running stage is pending' => [WorkflowInstanceStates::RUNNING, null, 'single', [WorkflowTaskStates::TODO], 'pending'];
        yield 'unfinished stage on canceled instance is skipped' => [WorkflowInstanceStates::CANCELED, null, 'single', [WorkflowTaskStates::TODO], 'skipped'];
    }

    /**
     * @param StaticEntityRepository<WorkflowTaskCollection>|null $tasks
     */
    private function subscriber(?StaticEntityRepository $tasks = null): ApprovalInstancePresentationSubscriber
    {
        $users = new WorkflowUserLookup(new StaticEntityRepository([
            new UserCollection(), new UserCollection(), new UserCollection(),
        ]));

        return new ApprovalInstancePresentationSubscriber(
            new WorkflowTaskFinder(
                $tasks ?? StaticEntityRepository::of(WorkflowTaskCollection::class, []),
                new StaticEntityRepository([new WorkflowTaskHistoryCollection()]),
                StaticEntityRepository::of(WorkflowLogCollection::class, []),
            ),
            new WorkflowTaskPresenter($users, StaticEntityRepository::of(WorkflowInstanceCollection::class, [])),
            $users,
            new StaticEntityRepository([new WorkflowCopyCollection()]),
            new ApprovalRoute(static::createStub(ConditionEvaluatorInterface::class)),
        );
    }
}
