<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowSummarySubscriber;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeStatusesEvent;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalWorkflowSummarySubscriber::class)]
class ApprovalWorkflowSummarySubscriberTest extends TestCase
{
    public function testReadsMultipleInstancesAndTheirCurrentReviewersInBatches(): void
    {
        $instances = new StaticEntityRepository([new WorkflowInstanceCollection([$this->instance('first'), $this->instance('second')])]);
        $tasks = new StaticEntityRepository([new WorkflowTaskCollection([$this->task('a', 'first'), $this->task('b', 'second'), $this->task('old-round', 'first', round: 0)])]);
        $copies = StaticEntityRepository::of(WorkflowCopyCollection::class, [new WorkflowCopyCollection()]);
        $users = new StaticEntityRepository([new UserCollection([$this->user()])]);
        $subscriber = new ApprovalWorkflowSummarySubscriber($instances, $tasks, $copies, new WorkflowUserLookup($users));
        $event = new WorkflowRuntimeStatusesEvent([
            'first' => ['releaseId' => 'release', 'publisher' => 'core:approval'],
            'second' => ['releaseId' => 'release', 'publisher' => 'core:approval'],
        ], $this->context('requester'));
        $subscriber->load($event);

        $statuses = $event->getStatuses();
        static::assertCount(2, $statuses);
        foreach ($statuses as $row) {
            static::assertSame('pending', $row['status']->status);
            static::assertSame([['id' => 'reviewer', 'name' => 'Finance reviewer']], $row['currentAssignees']);
            static::assertNotNull($row['status']->detailRoute);
            static::assertSame('sw.workflow.instance', $row['status']->detailRoute['name']);
        }
        static::assertCount(1, $instances->searchedCriteria);
        static::assertCount(1, $tasks->searchedCriteria);
        static::assertCount(1, $users->searchedCriteria);
        static::assertEqualsCanonicalizing(['first', 'second'], array_values($instances->searchedCriteria[0]->getIds()));
    }

    public function testDoesNotOfferDetailsToAnUnrelatedFinanceViewer(): void
    {
        $subscriber = new ApprovalWorkflowSummarySubscriber(
            new StaticEntityRepository([new WorkflowInstanceCollection([$this->instance('first')])]),
            new StaticEntityRepository([new WorkflowTaskCollection([$this->task('a', 'first')])]),
            StaticEntityRepository::of(WorkflowCopyCollection::class, [new WorkflowCopyCollection()]),
            new WorkflowUserLookup(new StaticEntityRepository([new UserCollection([$this->user()])])),
        );
        $event = new WorkflowRuntimeStatusesEvent(['first' => ['releaseId' => 'release', 'publisher' => 'core:approval']], $this->context('stranger'));
        $subscriber->load($event);
        static::assertNull($event->getStatuses()['first']['status']->detailRoute);
        static::assertSame('pending', $event->getStatuses()['first']['status']->status);
    }

    public function testDoesNotResolveAnInstanceAgainstAnotherRelease(): void
    {
        $tasks = StaticEntityRepository::of(WorkflowTaskCollection::class);
        $subscriber = new ApprovalWorkflowSummarySubscriber(
            new StaticEntityRepository([new WorkflowInstanceCollection([$this->instance('first')])]),
            $tasks,
            StaticEntityRepository::of(WorkflowCopyCollection::class),
            new WorkflowUserLookup(StaticEntityRepository::of(UserCollection::class)),
        );
        $event = new WorkflowRuntimeStatusesEvent(['first' => ['releaseId' => 'another-release', 'publisher' => 'core:approval']], $this->context('requester'));
        $subscriber->load($event);
        static::assertSame([], $event->getStatuses());
        static::assertSame([], $tasks->searchedCriteria);
    }

    public function testLeavesOtherRuntimesToTheirOwnSubscribers(): void
    {
        $instances = StaticEntityRepository::of(WorkflowInstanceCollection::class);
        $subscriber = new ApprovalWorkflowSummarySubscriber($instances, StaticEntityRepository::of(WorkflowTaskCollection::class), StaticEntityRepository::of(WorkflowCopyCollection::class), new WorkflowUserLookup(StaticEntityRepository::of(UserCollection::class)));
        $event = new WorkflowRuntimeStatusesEvent(['other' => ['releaseId' => 'release', 'publisher' => 'extension:runtime']], $this->context('requester'));
        $subscriber->load($event);
        static::assertSame([], $event->getStatuses());
        static::assertSame([], $instances->searchedCriteria);
    }

    private function context(string $actor): Context
    {
        $source = new AdminApiSource($actor);
        $source->setPermissions(['workflow_instance:access']);

        return Context::createDefaultContext($source);
    }

    private function instance(string $id): WorkflowInstanceEntity
    {
        $state = new StateMachineStateEntity();
        $state->setTechnicalName('running');
        $instance = new WorkflowInstanceEntity();
        $instance->assign(['id' => $id, 'definitionId' => 'release', 'initiatorId' => 'requester', 'currentNodeId' => 'finance', 'stateMachineState' => $state]);

        $instance->setUniqueIdentifier($id);

        return $instance;
    }

    private function task(string $id, string $instanceId, int $round = 1): WorkflowTaskEntity
    {
        $state = new StateMachineStateEntity();
        $state->setTechnicalName('todo');
        $task = new WorkflowTaskEntity();
        $task->assign(['id' => $id, 'instanceId' => $instanceId, 'assigneeId' => 'reviewer', 'round' => $round, 'nodeId' => 'finance', 'stateMachineState' => $state]);

        $task->setUniqueIdentifier($id);

        return $task;
    }

    private function user(): UserEntity
    {
        $user = new UserEntity();
        $user->setId('reviewer');
        $user->setName('Finance reviewer');

        return $user;
    }
}
