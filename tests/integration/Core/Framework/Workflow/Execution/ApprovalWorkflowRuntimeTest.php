<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\ApprovalWorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowStartedEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowEventQueue;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeException;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\_fixtures\RuntimeFixture;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalWorkflowRuntimeTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private ApprovalWorkflowRuntime $runtime;

    private ApprovalWorkflowService $flows;

    private WorkflowTaskFinder $tasks;

    private WorkflowEventQueue $events;

    private RuntimeFixture $fixture;

    private string $bindingId;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $runtime = static::getContainer()->get(ApprovalWorkflowRuntime::class);
        $flows = static::getContainer()->get(ApprovalWorkflowService::class);
        $tasks = static::getContainer()->get(WorkflowTaskFinder::class);
        $events = static::getContainer()->get(WorkflowEventQueue::class);
        static::assertInstanceOf(Connection::class, $connection);
        static::assertInstanceOf(ApprovalWorkflowRuntime::class, $runtime);
        static::assertInstanceOf(ApprovalWorkflowService::class, $flows);
        static::assertInstanceOf(WorkflowTaskFinder::class, $tasks);
        static::assertInstanceOf(WorkflowEventQueue::class, $events);
        $this->connection = $connection;
        $this->runtime = $runtime;
        $this->flows = $flows;
        $this->tasks = $tasks;
        $this->events = $events;
        $this->fixture = new RuntimeFixture($this->connection, $this->flows);
        $this->bindingId = Uuid::randomHex();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('system_config', ['id' => Uuid::fromHexToBytes($this->bindingId)]);
        $this->fixture->cleanup();
    }

    public function testStartAndBindingCommitBeforeRuntimeEventsAreDispatched(): void
    {
        $observer = DriverManager::getConnection($this->connection->getParams());
        $observed = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(WorkflowStartedEvent::EVENT_NAME, function (WorkflowStartedEvent $event) use ($observer, &$observed): void {
            $observed[] = [
                $event->getInstanceId(),
                $observer->fetchOne('SELECT configuration_value FROM system_config WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->bindingId)]),
            ];
        });

        try {
            $id = $this->runtime->transactional(function () use (&$observed): string {
                $id = $this->start();
                static::assertSame([], $observed);
                $this->connection->insert('system_config', [
                    'id' => Uuid::fromHexToBytes($this->bindingId),
                    'configuration_key' => 'runtime.test.' . $this->bindingId,
                    'configuration_value' => json_encode(['_value' => $id], \JSON_THROW_ON_ERROR),
                    'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);

                return $id;
            });
            static::assertCount(1, $observed);
            static::assertSame($id, $observed[0][0]);
            static::assertSame(['_value' => $id], json_decode($observed[0][1], true, flags: \JSON_THROW_ON_ERROR));
            $registry = static::getContainer()->get(WorkflowRuntimeRegistry::class);
            static::assertInstanceOf(WorkflowRuntimeRegistry::class, $registry);
            static::assertSame($this->runtime, $registry->get('core:approval'));
        } finally {
            $observer->close();
        }
    }

    public function testFailedBindingRollsBackInstanceTasksAndEvents(): void
    {
        $failure = new \RuntimeException('Binding rejected');
        $observed = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(WorkflowStartedEvent::EVENT_NAME, static function (object $event) use (&$observed): void {
            $observed[] = $event;
        });
        $this->expectExceptionObject($failure);

        try {
            $this->runtime->transactional(function () use ($failure): void {
                $this->start();

                throw $failure;
            });
        } finally {
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM workflow_instance WHERE definition_id = :id', ['id' => Uuid::fromHexToBytes($this->fixture->release->getId())]));
            static::assertSame([], $observed);
            static::assertTrue($this->events->isEmpty());
        }
    }

    public function testExistingOuterTransactionIsRejectedWithoutCallingTheOperation(): void
    {
        $this->connection->beginTransaction();
        $this->expectExceptionObject(WorkflowRuntimeException::enclosingTransaction());
        try {
            $this->runtime->transactional(static function (): void {
                static::fail('An enclosing transaction cannot safely dispatch runtime events.');
            });
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testBusinessViewerGetsStatusWithoutAnInaccessibleWorkflowLink(): void
    {
        $id = $this->start();
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions(['workflow_instance:access']);
        $status = $this->runtime->status($this->fixture->release, $id, new Context($source));

        static::assertSame('pending', $status->status);
        static::assertNull($status->detailRoute);
        static::assertFalse($status->canCancel);
        $initiator = $this->runtime->status($this->fixture->release, $id, $this->fixture->context);
        static::assertSame(['name' => 'sw.workflow.instance', 'params' => ['id' => $id]], $initiator->detailRoute);
        static::assertTrue($initiator->canCancel);
    }

    public function testParticipantWithoutWorkflowReadPrivilegeDoesNotReceiveWorkflowLink(): void
    {
        $id = $this->start();
        $status = $this->runtime->status($this->fixture->release, $id, new Context(new AdminApiSource($this->fixture->userId)));

        static::assertNull($status->detailRoute);
        static::assertTrue($status->canCancel);
    }

    public function testAnotherReleaseCannotAuthorizeAnInstance(): void
    {
        $id = $this->start();
        $release = clone $this->fixture->release;
        $release->setId(Uuid::randomHex());
        $this->expectExceptionObject(WorkflowRuntimeException::invalidReference());

        $this->runtime->status($release, $id, $this->fixture->context);
    }

    public function testStartRequiresAnAuthenticatedAdminActor(): void
    {
        $this->expectExceptionObject(WorkflowExecutionException::actorRequired());

        $this->runtime->start($this->fixture->release, [], 'Unauthenticated', Context::createDefaultContext());
    }

    public function testCancellationCannotImpersonateTheInitiator(): void
    {
        $id = $this->start();
        $this->expectExceptionObject(WorkflowExecutionException::forbidden('只有发起人可以撤销'));

        $this->runtime->cancel($this->fixture->release, $id, 'Stop', new Context(new AdminApiSource(Uuid::randomHex())));
    }

    public function testStaleBusinessSnapshotCannotCancelAConcurrentlyApprovedInstance(): void
    {
        $id = $this->start();
        $task = $this->tasks->forInstance($id, $this->fixture->context)->first();
        static::assertNotNull($task);
        $this->expectExceptionObject(WorkflowExecutionException::instanceNotRunning($id));

        try {
            $this->runtime->transactional(function () use ($id, $task): void {
                // Simulate finance having already read its business record in this transaction.
                static::assertTrue($this->flows->loadInstance($id, $this->fixture->context)->isRunning());
                $this->approveInAnotherProcess($task->id);
                static::assertTrue($this->flows->loadInstance($id, $this->fixture->context)->isRunning());
                $this->flows->cancel($id, 'Withdraw transfer', $this->fixture->context);
            });
        } finally {
            static::assertSame('agreed', $this->flows->loadInstance($id, $this->fixture->context)->state());
            static::assertSame('agreed', $this->tasks->forInstance($id, $this->fixture->context)->first()?->state());
        }
    }

    public function testStaleBusinessSnapshotCancelsNewStageWithoutRewritingTheApprovedTask(): void
    {
        $graph = $this->flows->loadDefinition($this->fixture->release->getId(), $this->fixture->context)->graph();
        $graph['nodes'][] = ['id' => 'next', 'type' => 'snaker:task', 'properties' => ['name' => 'Next approval', 'assigneeType' => 'user', 'assigneeValue' => [$this->fixture->userId], 'countersignType' => 'single']];
        foreach ($graph['edges'] as &$edge) {
            if ($edge['sourceNodeId'] === 'approval') {
                $edge['targetNodeId'] = 'next';
            }
        }
        unset($edge);
        $graph['edges'][] = ['id' => 'next-end', 'sourceNodeId' => 'next', 'targetNodeId' => 'end'];
        $this->flows->saveDefinition($this->fixture->release->getId(), $graph, null, Context::createDefaultContext());
        $id = $this->start();
        $task = $this->tasks->forInstance($id, $this->fixture->context)->first();
        static::assertNotNull($task);

        $this->runtime->transactional(function () use ($id, $task): void {
            static::assertCount(1, $this->tasks->forInstance($id, $this->fixture->context));
            $this->approveInAnotherProcess($task->id);
            static::assertCount(1, $this->tasks->forInstance($id, $this->fixture->context));
            $this->flows->cancel($id, 'Withdraw transfer', $this->fixture->context);
        });

        static::assertSame('canceled', $this->flows->loadInstance($id, $this->fixture->context)->state());
        $tasks = $this->tasks->forInstance($id, $this->fixture->context);
        static::assertCount(2, $tasks);
        static::assertSame(1, $tasks->countByState('agreed'));
        static::assertSame(1, $tasks->countByState('canceled'));
        static::assertSame(0, $tasks->countByState('todo'));
        static::assertSame('agree', $this->tasks->historyOf($id, $this->fixture->context)->first()?->decision);
    }

    public function testFailedBusinessCancellationRollsBackRuntimeTimestampsAndStates(): void
    {
        $id = $this->start();
        $failure = new \RuntimeException('Business cancellation failed');
        $this->expectExceptionObject($failure);

        try {
            $this->runtime->transactional(function () use ($id, $failure): void {
                $this->flows->cancel($id, 'Withdraw transfer', $this->fixture->context);

                throw $failure;
            });
        } finally {
            $instance = $this->flows->loadInstance($id, $this->fixture->context);
            $task = $this->tasks->forInstance($id, $this->fixture->context)->first();
            static::assertNotNull($task);
            static::assertSame('running', $instance->state());
            static::assertNull($instance->endedAt);
            static::assertSame('todo', $task->state());
            static::assertNull($task->completedAt);
        }
    }

    #[DataProvider('decisions')]
    public function testRuntimeReflectsTheActualApprovalOutcome(string $decision, string $expected): void
    {
        $id = $this->start();
        $task = $this->tasks->forInstance($id, $this->fixture->context)->first();
        static::assertNotNull($task);
        if ($decision === 'cancel') {
            $this->runtime->transactional(fn () => $this->runtime->cancel($this->fixture->release, $id, 'Stop', $this->fixture->context));
        } elseif ($decision === 'approve') {
            $this->flows->approve($task->id, null, $this->fixture->context);
        } else {
            $this->flows->reject($task->id, 'Not approved', $this->fixture->context);
        }

        $status = $this->runtime->status($this->fixture->release, $id, $this->fixture->context);
        static::assertSame($expected, $status->status);
        static::assertFalse($status->canCancel);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function decisions(): iterable
    {
        yield 'reviewer approves' => ['approve', 'approved'];
        yield 'reviewer rejects' => ['reject', 'rejected'];
        yield 'initiator withdraws' => ['cancel', 'canceled'];
    }

    private function start(): string
    {
        return $this->runtime->start($this->fixture->release, ['reason' => 'Transfer approval'], 'Finance approval', $this->fixture->context);
    }

    private function approveInAnotherProcess(string $taskId): void
    {
        $code = <<<'PHP'
$loader = require getcwd() . '/vendor/autoload.php';
Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager::prepare($loader);
$container = Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$source = new Shopwell\Core\Framework\Api\Context\AdminApiSource($argv[2]);
$source->setIsAdmin(true);
$container->get(Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService::class)->approve($argv[1], 'Concurrent approval', new Shopwell\Core\Framework\Context($source));
PHP;
        $directory = static::getContainer()->getParameter('kernel.project_dir');
        static::assertIsString($directory);
        $process = new Process([\PHP_BINARY, '-d', 'error_reporting=22527', '-r', $code, $taskId, $this->fixture->userId], $directory, [
            'DATABASE_URL' => $_SERVER['DATABASE_URL'], 'APP_ENV' => 'test', 'APP_DEBUG' => '1', 'XDEBUG_MODE' => 'off',
        ], timeout: 30);
        $process->run();
        static::assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
    }
}
