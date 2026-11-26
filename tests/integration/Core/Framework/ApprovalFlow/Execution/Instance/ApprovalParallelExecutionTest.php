<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalProgressionRunner;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Event\ApprovalReturnTargetsEvent;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskCompletionReason;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Request\ApprovalRequestService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Transfer\Approval\PaymentTransferApprovalBindingSubscriber;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ParallelApprovalFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalParallelExecutionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalInstanceService $instances;

    private string $reviewerId;

    private Context $reviewer;

    private string $instanceId;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-parallel-' . Uuid::randomHex();
        $manifest = ParallelApprovalFixture::manifest($this->filesystem, $this->directory);
        $this->runtime = ParallelApprovalFixture::container($kernel, $manifest);
        $this->instances = $this->runtime->get(ApprovalInstanceService::class);
        $this->reviewerId = TestUser::createNewTestUser($this->connection)->getUserId();
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $this->reviewer = Context::createDefaultContext($source);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testBothBranchesStartAndOnlyTheLastArrivalContinues(): void
    {
        $release = ApprovalRuntimeFixture::publish($this->runtime, $this->runtime, ParallelApprovalFixture::graph($this->reviewerId));
        $this->instanceId = InstanceFixture::create($this->connection, $release, ApprovalInstanceStatus::Draft);
        $commandId = Uuid::randomHex();
        $store = $this->runtime->get(ApprovalInstanceStore::class);
        $started = $store->start($this->instanceId, $commandId, 1, '{"source":"system"}', Context::createDefaultContext());

        static::assertSame(2, $this->pendingTasks());
        static::assertEquals($started, $store->start($this->instanceId, $commandId, 1, '{"source":"system"}', Context::createDefaultContext()));
        static::assertSame(2, $this->pendingTasks());
        $first = $this->vote('finance', 2, ApprovalOutcome::Approved);
        $waiting = $this->instances->progress($this->instanceId, $first, Uuid::randomHex(), 3, Context::createDefaultContext());

        static::assertSame(ApprovalInstanceStatus::Running, $waiting->status);
        static::assertSame(1, $this->pendingTasks());
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        $second = $this->vote('manager', 4, ApprovalOutcome::Approved);
        $finished = $this->instances->progress($this->instanceId, $second, Uuid::randomHex(), 5, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Approved, $finished->outcome);
        static::assertSame(0, $this->pendingTasks());
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution WHERE node_id = :node', ['node' => 'join']));
    }

    public function testReturningAnApprovalReopensOnlyItsBranch(): void
    {
        $events = EventHookDispatcher::fromContainer(static::getContainer());
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, secondReviews: true));
        $system = Context::createDefaultContext();
        $finance = $this->vote('finance', 2, ApprovalOutcome::Approved);
        $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 3, $system);
        $manager = $this->vote('manager', 4, ApprovalOutcome::Approved);
        $this->instances->progress($this->instanceId, $manager, Uuid::randomHex(), 5, $system);

        $taskId = $this->pendingTask('finance-second')['id'];
        $sibling = $this->pendingTask('manager-second');
        $listener = static function (ApprovalReturnTargetsEvent $event) use ($manager): void {
            $event->restrictTo([$manager]);
        };
        $events->on(ApprovalReturnTargetsEvent::class, $listener);
        static::assertSame([], $this->instances->returnTargets($this->instanceId, $taskId, $this->reviewer));
        try {
            $this->instances->returnApproval($this->instanceId, $taskId, Uuid::randomHex(), 6, $finance, 'Check again.', $this->reviewer);
            static::fail('The listener excluded the finance target.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::RETURN_TARGET_UNAVAILABLE, $exception->getErrorCode());
        }
        $events->off(ApprovalReturnTargetsEvent::class, $listener);
        static::assertSame([$finance], array_column($this->instances->returnTargets($this->instanceId, $taskId, $this->reviewer), 'executionId'));
        try {
            $this->instances->returnApproval($this->instanceId, $taskId, Uuid::randomHex(), 6, $manager, 'Check again.', $this->reviewer);
            static::fail('A task cannot return to a sibling branch.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::RETURN_TARGET_UNAVAILABLE, $exception->getErrorCode());
        }

        $receipt = $this->instances->returnApproval($this->instanceId, $taskId, Uuid::randomHex(), 6, $finance, 'Check again.', $this->reviewer);

        static::assertSame(ApprovalInstanceStatus::Running, $receipt->status);
        static::assertSame(7, $receipt->aggregateVersion);
        static::assertSame($sibling, $this->pendingTask('manager-second'));
        static::assertSame(2, $this->pendingTasks());
        static::assertSame(ApprovalTaskCompletionReason::RETURNED, $this->connection->fetchOne('SELECT completion_reason FROM approval_flow_task WHERE id = :id', ['id' => Uuid::fromHexToBytes($taskId)]));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = \'approved\''));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));

        $reopened = $this->vote('finance', 7, ApprovalOutcome::Approved);
        static::assertNotSame($finance, $reopened);
        $this->instances->progress($this->instanceId, $reopened, Uuid::randomHex(), 8, $system);
        $financeSecond = $this->vote('finance-second', 9, ApprovalOutcome::Approved);
        $waiting = $this->instances->progress($this->instanceId, $financeSecond, Uuid::randomHex(), 10, $system);
        static::assertSame(ApprovalInstanceStatus::Running, $waiting->status);
        static::assertSame($sibling, $this->pendingTask('manager-second'));
        $managerSecond = $this->vote('manager-second', 11, ApprovalOutcome::Approved);
        $completed = $this->instances->progress($this->instanceId, $managerSecond, Uuid::randomHex(), 12, $system);
        static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
    }

    public function testOnlyActuallySelectedBranchesBelongToTheJoin(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, outputs: ['left']));
        static::assertSame(1, $this->pendingTasks());
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_branch'));
        $first = $this->vote('finance', 2, ApprovalOutcome::Approved);

        $result = $this->instances->progress($this->instanceId, $first, Uuid::randomHex(), 3, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Approved, $result->outcome);
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution WHERE node_id = :node', ['node' => 'manager']));
    }

    public function testAnInnerJoinReturnsToItsOuterBranchWithoutCompletingTheProcess(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, nested: true));
        static::assertSame(3, $this->pendingTasks());
        $finance = $this->vote('inner-finance', 2, ApprovalOutcome::Approved);
        $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 3, Context::createDefaultContext());
        $legal = $this->vote('inner-legal', 4, ApprovalOutcome::Approved);

        $waiting = $this->instances->progress($this->instanceId, $legal, Uuid::randomHex(), 5, Context::createDefaultContext());

        static::assertSame(ApprovalInstanceStatus::Running, $waiting->status);
        static::assertSame(1, $this->pendingTasks());
        static::assertSame(['open', 'completed'], $this->connection->fetchFirstColumn('SELECT status FROM approval_flow_execution_scope ORDER BY join_node_id DESC'));
        $manager = $this->vote('manager', 6, ApprovalOutcome::Approved);
        $completed = $this->instances->progress($this->instanceId, $manager, Uuid::randomHex(), 7, Context::createDefaultContext());
        static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_scope WHERE status = :status', ['status' => 'completed']));
    }

    public function testTheJoinExtensionCanContinueEarlyAndCancelTheRemainingTask(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, minimum: 1));
        $finance = $this->vote('finance', 2, ApprovalOutcome::Approved);

        $completed = $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 3, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
        static::assertSame(0, $this->pendingTasks());
        static::assertSame(ApprovalTaskCompletionReason::SCOPE_RESOLVED, $this->connection->fetchOne('SELECT completion_reason FROM approval_flow_task WHERE status = :status', ['status' => 'cancelled']));
        static::assertSame(['arrived', 'cancelled'], $this->connection->fetchFirstColumn('SELECT status FROM approval_flow_execution_branch ORDER BY status'));
    }

    public function testEarlyJoinPreservesAnAlreadyCommittedSiblingOpinionAndEndsItsDispatchEligibility(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, minimum: 1));
        $manager = $this->vote('manager', 2, ApprovalOutcome::Approved);
        $finance = $this->vote('finance', 3, ApprovalOutcome::Approved);

        $completed = $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 4, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
        static::assertSame('approved', $this->connection->fetchOne('SELECT outcome FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($manager)]));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT advanced FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($manager)]));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'approved']));
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'completed', 'progress'));
        $this->instances->progress($this->instanceId, $manager, Uuid::randomHex(), 5, Context::createDefaultContext());
    }

    public function testACommittedVetoWinsEvenWhenTheApprovedBranchIsProgressedFirst(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, minimum: 1));
        $this->vote('manager', 2, ApprovalOutcome::Rejected);
        $finance = $this->vote('finance', 3, ApprovalOutcome::Approved);

        $completed = $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 4, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Rejected, $completed->outcome);
        static::assertSame('cancelled', $this->connection->fetchOne('SELECT status FROM approval_flow_execution WHERE node_id = :node', ['node' => 'join']));
        static::assertSame('cancelled', $this->connection->fetchOne('SELECT status FROM approval_flow_execution_scope'));
    }

    public function testARejectedBranchCancelsTheOtherPendingReviewWithoutInventingAnOpinion(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId));
        $finance = $this->vote('finance', 2, ApprovalOutcome::Rejected);

        $completed = $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 3, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Rejected, $completed->outcome);
        static::assertSame(0, $this->pendingTasks());
        static::assertSame(ApprovalTaskCompletionReason::PROCESS_REJECTED, $this->connection->fetchOne('SELECT completion_reason FROM approval_flow_task WHERE status = :status', ['status' => 'cancelled']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'rejected']));
    }

    public function testAnInnerScopeCannotReuseTheOuterReturnBoundary(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('inner-fork', 'A distinct return boundary inside the current region is required.'));
        try {
            $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, nested: true, reuseBoundary: true));
        } finally {
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_scope'));
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance'));
        }
    }

    public function testAStaleArrivalCommandDoesNotChangeTheScopeOrLoseTheSavedVote(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId));
        $finance = $this->vote('finance', 2, ApprovalOutcome::Approved);
        $this->expectExceptionObject(ApprovalFlowException::instanceVersionConflict($this->instanceId, 2, 3));
        try {
            $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 2, Context::createDefaultContext());
        } finally {
            static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_branch WHERE status = :status', ['status' => 'pending']));
            static::assertSame('approved', $this->connection->fetchOne('SELECT outcome FROM approval_flow_execution WHERE id = :id', ['id' => Uuid::fromHexToBytes($finance)]));
        }
    }

    public function testResultFailureRollsBackTheLastArrivalAndCanReplayTheSameCommand(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId));
        $finance = $this->vote('finance', 2, ApprovalOutcome::Approved);
        $this->instances->progress($this->instanceId, $finance, Uuid::randomHex(), 3, Context::createDefaultContext());
        $manager = $this->vote('manager', 4, ApprovalOutcome::Approved);
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_result.written', static function (): void {
            throw new \RuntimeException('Parallel result write failed.');
        }, once: true);
        $commandId = Uuid::randomHex();
        try {
            $this->instances->progress($this->instanceId, $manager, $commandId, 5, Context::createDefaultContext());
            static::fail('The last arrival and scope closure must roll back with the result.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Parallel result write failed.', $exception->getMessage());
        }
        static::assertSame('open', $this->connection->fetchOne('SELECT status FROM approval_flow_execution_scope'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_branch WHERE status = :status', ['status' => 'arrived']));
        static::assertSame('awaiting_signal', $this->connection->fetchOne('SELECT status FROM approval_flow_execution WHERE node_id = :node', ['node' => 'join']));
        $receipt = $this->instances->progress($this->instanceId, $manager, $commandId, 5, Context::createDefaultContext());
        static::assertSame(ApprovalOutcome::Approved, $receipt->outcome);
        static::assertEquals($receipt, $this->instances->progress($this->instanceId, $manager, $commandId, 5, Context::createDefaultContext()));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_outbox'));
    }

    public function testScheduledProgressionConsumesBothCommittedBranchesInOneRun(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId));
        $this->vote('finance', 2, ApprovalOutcome::Approved);
        $this->vote('manager', 3, ApprovalOutcome::Approved);
        $runner = new ApprovalProgressionRunner($this->runtime->get(ApprovalInstanceStore::class), $this->instances, new NullLogger());

        $runner->run();

        static::assertSame('completed', $this->connection->fetchOne('SELECT status FROM approval_flow_instance'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        $runner->run();
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
    }

    public function testAnEarlyOuterJoinCancelsNestedTasksAndTheirWaitingSubscription(): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, minimum: 1, nested: true));
        $manager = $this->vote('manager', 2, ApprovalOutcome::Approved);

        $completed = $this->instances->progress($this->instanceId, $manager, Uuid::randomHex(), 3, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'cancelled']));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution WHERE advanced = 0'));
        static::assertSame('cancelled', $this->connection->fetchOne('SELECT status FROM approval_flow_execution WHERE node_id = :node', ['node' => 'inner-join']));
        static::assertSame(['completed', 'cancelled'], $this->connection->fetchFirstColumn('SELECT status FROM approval_flow_execution_scope ORDER BY join_node_id DESC'));
    }

    public function testEverySelectedBranchMustReturnBeforeTheProcessEnd(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionScope('fork', 'Selected branches must be disjoint and return before an enclosing boundary or process end.'));
        try {
            $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId, escapeBoundary: true));
        } finally {
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_scope'));
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
        }
    }

    public function testAnAdmittedTransferRequestStartsBothBranchesAndReplaysItsOriginalReceipt(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new PaymentTransferApprovalBindingSubscriber($this->runtime->get(NodeTypeRegistry::class)));
        $this->runtime->set('event_dispatcher', $dispatcher);
        $release = ApprovalRuntimeFixture::publish($this->runtime, $this->runtime, ParallelApprovalFixture::graph($this->reviewerId));
        $bindings = $this->runtime->get(ApprovalBindingService::class);
        $bindings->save('payment:transfer', '1.0.0', $release, 0, Context::createDefaultContext());
        $bindings->activate('payment:transfer', 1, 0, Context::createDefaultContext());
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions(['approval_flow_request:create', 'approval_flow_instance:create']);
        $context = Context::createDefaultContext($source);
        $requests = $this->runtime->get(ApprovalRequestService::class);
        $request = $requests->create(
            'payment:transfer',
            Uuid::randomHex(),
            1,
            Hasher::hash('parallel transfer revision', 'sha256'),
            '{"amount":100,"currency":"CNY","recipient":"Recipient","paymentAccount":"Merchant","reason":null}',
            $context,
        );

        $receipt = $requests->start($request->getId(), $context);

        static::assertSame(2, $receipt->aggregateVersion);
        static::assertSame(2, $this->pendingTasks());
        static::assertEquals($receipt, $requests->start($request->getId(), $context));
        static::assertSame(2, $this->pendingTasks());
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution_scope'));
    }

    #[DataProvider('guardedScopeWrites')]
    public function testGenericDalCannotRewriteOrDeleteScopeFacts(string $entity, bool $delete): void
    {
        $this->startGraph(ParallelApprovalFixture::graph($this->reviewerId));
        $id = Uuid::fromBytesToHex($this->connection->fetchOne('SELECT id FROM ' . $entity . ' LIMIT 1'));
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository($entity);
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        if ($delete) {
            $repository->delete([['id' => $id]], Context::createDefaultContext());
        } else {
            $repository->update([['id' => $id, 'status' => 'cancelled']], Context::createDefaultContext());
        }
    }

    public static function guardedScopeWrites(): iterable
    {
        yield 'scope status cannot bypass its command' => ['approval_flow_execution_scope', false];
        yield 'branch arrival cannot bypass its command' => ['approval_flow_execution_branch', false];
        yield 'scope history cannot be deleted' => ['approval_flow_execution_scope', true];
        yield 'branch history cannot be deleted' => ['approval_flow_execution_branch', true];
    }

    private function startGraph(string $graph): ApprovalInstanceCommandResult
    {
        $release = ApprovalRuntimeFixture::publish($this->runtime, $this->runtime, $graph);
        $this->instanceId = InstanceFixture::create($this->connection, $release, ApprovalInstanceStatus::Draft);

        return $this->runtime->get(ApprovalInstanceStore::class)->start($this->instanceId, Uuid::randomHex(), 1, '{"source":"system"}', Context::createDefaultContext());
    }

    private function pendingTasks(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'pending']);
    }

    private function vote(string $nodeId, int $version, ApprovalOutcome $opinion): string
    {
        $task = $this->pendingTask($nodeId);
        $this->instances->decide($this->instanceId, $task['id'], Uuid::randomHex(), $version, $opinion, '', $this->reviewer);

        return $task['executionId'];
    }

    /**
     * @return array{id: string, executionId: string}
     */
    private function pendingTask(string $nodeId): array
    {
        $task = $this->connection->fetchAssociative(
            'SELECT task.id, task.execution_id FROM approval_flow_task task INNER JOIN approval_flow_execution execution ON execution.id = task.execution_id WHERE execution.instance_id = :instance AND execution.node_id = :node AND task.status = :pending',
            ['instance' => Uuid::fromHexToBytes($this->instanceId), 'node' => $nodeId, 'pending' => 'pending'],
        );
        static::assertNotFalse($task);

        return ['id' => Uuid::fromBytesToHex($task['id']), 'executionId' => Uuid::fromBytesToHex($task['execution_id'])];
    }
}
