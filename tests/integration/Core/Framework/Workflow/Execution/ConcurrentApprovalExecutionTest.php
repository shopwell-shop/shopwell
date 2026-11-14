<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowCountersignTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalCondition;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;
use Symfony\Component\Process\Process;

/**
 * Uses committed, isolated fixtures so independent processes can compete for the
 * same instance lock. Only this test's generated IDs are removed afterward.
 *
 * @internal
 */
#[Package('framework')]
class ConcurrentApprovalExecutionTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return iterable<string, array{string}>
     */
    public static function modes(): iterable
    {
        yield 'all approvals survive a lock wait' => [WorkflowCountersignTypes::COUNTERSIGN];
        yield 'first decision closes a competing sibling' => [WorkflowCountersignTypes::FIRST_DECISION];
    }

    #[DataProvider('modes')]
    public function testCompetingDecisionsReadStateCommittedByThePreviousHolder(string $mode): void
    {
        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(0, $connection->getTransactionNestingLevel());
        $context = Context::createDefaultContext();
        $userIds = [Uuid::randomHex(), Uuid::randomHex()];
        $definitionId = null;
        $instanceId = null;
        $taskIds = [];
        $processes = [];

        try {
            $localeId = Uuid::fromBytesToHex($connection->fetchOne('SELECT id FROM locale LIMIT 1'));
            foreach ($userIds as $userId) {
                static::getContainer()->get('user.repository')->create([[
                    'id' => $userId, 'username' => 'concurrent-' . $userId,
                    'name' => 'Concurrent approval test', 'email' => $userId . '@example.com',
                    'password' => 'A-strong-test-password-123!', 'localeId' => $localeId, 'active' => true,
                ]], $context);
            }
            $source = new AdminApiSource($userIds[0]);
            $source->setIsAdmin(true);
            $actorContext = new Context($source);
            $service = static::getContainer()->get(ApprovalWorkflowService::class);
            $schema = [['key' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'required' => true]];
            $condition = (new ApprovalCondition())->encode(['field' => 'request.amount', 'fieldType' => 'amount', 'operator' => 'greaterThan', 'value' => '5000'], $schema);
            $definitionId = $service->createDefinition([
                'code' => 'concurrent-' . Uuid::randomHex(), 'name' => 'Concurrent approval', 'formSchema' => $schema, 'graphData' => [
                    'nodes' => [
                        ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => $schema]],
                        ['id' => 'threshold', 'type' => 'snaker:condition', 'properties' => []],
                        ['id' => 'approval', 'type' => 'snaker:task', 'properties' => ['name' => 'Approval', 'assigneeType' => 'user', 'assigneeValue' => $userIds, 'countersignType' => $mode]],
                        ['id' => 'next', 'type' => 'snaker:task', 'properties' => ['name' => 'Next approval', 'assigneeType' => 'user', 'assigneeValue' => [$userIds[0]], 'countersignType' => 'single']],
                        ['id' => 'end', 'type' => 'snaker:end', 'properties' => []],
                    ],
                    'edges' => [
                        ['id' => 'start-approval', 'sourceNodeId' => 'start', 'targetNodeId' => 'approval', 'properties' => []],
                        ['id' => 'approval-threshold', 'sourceNodeId' => 'approval', 'targetNodeId' => 'threshold', 'properties' => []],
                        ['id' => 'threshold-next', 'sourceNodeId' => 'threshold', 'targetNodeId' => 'next', 'properties' => ['expr' => $condition]],
                        ['id' => 'threshold-end', 'sourceNodeId' => 'threshold', 'targetNodeId' => 'end', 'properties' => []],
                        ['id' => 'next-end', 'sourceNodeId' => 'next', 'targetNodeId' => 'end', 'properties' => []],
                    ],
                ],
            ], $context);
            $service->publishDefinition($definitionId, $context);
            $instanceId = $service->start($definitionId, ['amount' => '5000.01'], 'Concurrent approval', $actorContext);
            $finder = static::getContainer()->get(WorkflowTaskFinder::class);
            $tasks = $finder->forInstance($instanceId, $context)->getElements();
            $tasks = array_values($tasks);
            $taskIds = array_map(static fn ($task): string => $task->id, $tasks);
            static::assertCount(2, $tasks);

            // Hold the instance until both workers are visibly waiting on its row.
            // Under the old read-before-lock order, both transactions already had
            // a stale REPEATABLE READ snapshot at this point.
            $connection->beginTransaction();
            $connection->fetchOne('SELECT id FROM workflow_instance WHERE id = :id FOR UPDATE', ['id' => Uuid::fromHexToBytes($instanceId)]);
            foreach ($tasks as $index => $task) {
                $decision = $mode === WorkflowCountersignTypes::FIRST_DECISION && $index === 1 ? 'reject' : 'approve';
                $process = $this->worker($task->id, $task->assigneeId, $decision);
                $process->start();
                $processes[] = $process;
            }
            $this->waitForBothInstanceLocks($connection, $processes);
            $connection->commit();

            $outcomes = [];
            foreach ($processes as $process) {
                static::assertSame(0, $process->wait(), $process->getErrorOutput());
                preg_match('/RESULT:(.+)/', $process->getOutput(), $matches);
                static::assertArrayHasKey(1, $matches, $process->getOutput() . $process->getErrorOutput());
                $outcomes[] = $matches[1];
            }
            $instance = $service->loadInstance($instanceId, $context);
            $allTasks = $finder->forInstance($instanceId, $context);
            $taskIds = array_map(static fn ($task): string => $task->id, array_values($allTasks->getElements()));
            $advanced = $mode === WorkflowCountersignTypes::COUNTERSIGN || $finder->historyOf($instanceId, $context)->first()?->decision === 'agree';
            static::assertSame($advanced ? 1 : 0, $allTasks->countByState(WorkflowTaskStates::TODO));
            static::assertCount($advanced ? 3 : 2, $allTasks);
            static::assertSame($advanced ? 'next' : 'approval', $instance->currentNodeId);
            if ($mode === WorkflowCountersignTypes::COUNTERSIGN) {
                static::assertSame(['ok', 'ok'], $outcomes);
                static::assertSame('running', $instance->stateMachineState?->getTechnicalName());
                static::assertCount(2, $finder->historyOf($instanceId, $context));
            } else {
                sort($outcomes);
                static::assertSame([WorkflowExecutionException::TASK_ALREADY_HANDLED, 'ok'], $outcomes);
                static::assertSame(1, $allTasks->countByState(WorkflowTaskStates::CANCELED));
                $history = $finder->historyOf($instanceId, $context);
                static::assertCount(1, $history);
                static::assertSame($history->first()?->decision === 'agree' ? 'running' : 'rejected', $instance->stateMachineState?->getTechnicalName());
            }
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($processes as $process) {
                $process->stop();
            }
            $entityIds = array_filter([$instanceId, ...$taskIds], static fn (?string $id): bool => $id !== null);
            if ($entityIds !== []) {
                $connection->executeStatement('DELETE FROM state_machine_history WHERE entity_name IN (:entities) AND referenced_id IN (:ids)', ['entities' => ['workflow_instance', 'workflow_task'], 'ids' => array_map(Uuid::fromHexToBytes(...), $entityIds)], ['entities' => ArrayParameterType::STRING, 'ids' => ArrayParameterType::BINARY]);
            }
            if ($instanceId !== null) {
                $connection->delete('workflow_instance', ['id' => Uuid::fromHexToBytes($instanceId)]);
            }
            if ($definitionId !== null) {
                $connection->delete('workflow_execution_definition', ['id' => Uuid::fromHexToBytes($definitionId)]);
            }
            foreach ($userIds as $userId) {
                $connection->delete('user', ['id' => Uuid::fromHexToBytes($userId)]);
            }
        }
    }

    private function worker(string $taskId, string $actorId, string $decision): Process
    {
        $code = <<<'CODE'
$loader = require getcwd() . '/vendor/autoload.php';
Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager::prepare($loader);
$container = Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager::getKernel()->getContainer()->get('test.service_container');
$connection = $container->get(Doctrine\DBAL\Connection::class);
$connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 45');
echo 'READY:' . $connection->fetchOne('SELECT CONNECTION_ID()') . PHP_EOL;
$source = new Shopwell\Core\Framework\Api\Context\AdminApiSource($argv[2]);
$source->setIsAdmin(true);
try {
    $container->get(Shopwell\Core\Framework\Workflow\Node\Approval\ApprovalWorkflowService::class)->{$argv[3]}($argv[1], 'Concurrent decision', new Shopwell\Core\Framework\Context($source));
    echo 'RESULT:ok' . PHP_EOL;
} catch (Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException $exception) {
    echo 'RESULT:' . $exception->getErrorCode() . PHP_EOL;
}
CODE;

        return new Process([\PHP_BINARY, '-d', 'error_reporting=22527', '-r', $code, $taskId, $actorId, $decision], static::getContainer()->getParameter('kernel.project_dir'), [
            'DATABASE_URL' => $_SERVER['DATABASE_URL'], 'APP_ENV' => 'test', 'APP_DEBUG' => '1', 'XDEBUG_MODE' => 'off',
        ], timeout: 60);
    }

    /**
     * @param list<Process> $processes
     */
    private function waitForBothInstanceLocks(Connection $connection, array $processes): void
    {
        $deadline = microtime(true) + 30;
        do {
            $waiting = 0;
            foreach ($processes as $process) {
                if (preg_match('/READY:(\d+)/', $process->getOutput(), $matches) !== 1) {
                    continue;
                }
                $query = $connection->fetchOne('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID = :id', ['id' => (int) $matches[1]]);
                if (\is_string($query) && str_contains($query, 'workflow_instance') && str_contains($query, 'FOR UPDATE')) {
                    ++$waiting;
                }
            }
            if ($waiting === 2) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        static::fail('Both workers must reach the instance lock: ' . implode('\n', array_map(static fn (Process $process): string => $process->getOutput() . $process->getErrorOutput(), $processes)));
    }
}
