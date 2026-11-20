<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalInstanceConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('competingCommands')]
    public function testCompetingCommandUsesTheLatestStateAndReceiptDespiteAnOlderSnapshot(bool $sameCommand, bool $enterStart = false): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $releaseId = InstanceFixture::publish($container);
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $instanceId = InstanceFixture::create($connection, $releaseId, ApprovalInstanceStatus::Running);
        $commandId = Uuid::randomHex();
        $contendingCommandId = $sameCommand ? $commandId : Uuid::randomHex();
        $input = new InputStream();
        $writer = new Process([
            \PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-command.php',
            $instanceId, $contendingCommandId, $enterStart ? 'enter-start' : 'suspend',
        ]);
        $writer->setInput($input);
        $writer->setTimeout(30);

        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT:running:0');
            $connection->beginTransaction();
            $container->get(ApprovalInstanceService::class)->suspend($instanceId, $commandId, 1, 'Review required.', Context::createDefaultContext());
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString($sameCommand ? 'RECEIPT:suspended:2' : 'CONFLICT:2', $writer->getOutput());
            static::assertSame('suspended', $connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
            static::assertSame(2, (int) $connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->delete('approval_flow_operation', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_instance', ['id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($releaseId)]);
            $connection->delete('approval_flow_draft', ['definition_id' => $definitionId]);
            $connection->delete('approval_flow_definition', ['id' => $definitionId]);
        }
    }

    public static function competingCommands(): iterable
    {
        yield 'same command replays its committed receipt' => [true];
        yield 'another suspension sees the committed version conflict' => [false];
        yield 'start entry waits for suspension and sees the committed version conflict' => [false, true];
    }

    #[DataProvider('groupPolicies')]
    public function testConcurrentDecisionsUseTheLatestVotesDespiteAnOlderSnapshot(string $policy): void
    {
        $kernel = static::getContainer();
        $connection = $kernel->get(Connection::class);
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-votes-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($filesystem, $directory);
        $runtime = ApprovalRuntimeFixture::container($kernel, $manifest);
        $first = TestUser::createNewTestUser($connection)->getUserId();
        $second = TestUser::createNewTestUser($connection)->getUserId();
        $graph = str_replace(['"finance-user"', '"core:all"'], [json_encode($first, \JSON_THROW_ON_ERROR) . ',' . json_encode($second, \JSON_THROW_ON_ERROR), json_encode($policy, \JSON_THROW_ON_ERROR)], GraphFixture::json());
        $releaseId = ApprovalRuntimeFixture::publish($kernel, $runtime, $graph);
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $instanceId = InstanceFixture::create($connection, $releaseId, ApprovalInstanceStatus::Running);
        $service = $runtime->get(ApprovalInstanceService::class);
        $service->enterStart($instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());
        $executionId = $connection->fetchOne('SELECT id FROM approval_flow_execution WHERE instance_id = :instance AND node_id = :node', ['instance' => Uuid::fromHexToBytes($instanceId), 'node' => 'review']);
        static::assertIsString($executionId);
        $service->enterApproval($instanceId, Uuid::fromBytesToHex($executionId), Uuid::randomHex(), 2, Context::createDefaultContext());
        $tasks = $connection->fetchAllKeyValue('SELECT assignee_id, id FROM approval_flow_task WHERE execution_id = :execution', ['execution' => $executionId]);
        $source = new AdminApiSource($first);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $input = new InputStream();
        $writer = new Process([
            \PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-decision.php',
            $instanceId, Uuid::fromBytesToHex($tasks[Uuid::fromHexToBytes($second)]), $second,
        ]);
        $writer->setInput($input);
        $writer->setTimeout(30);

        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT:pending');
            $connection->beginTransaction();
            $service->decide($instanceId, Uuid::fromBytesToHex($tasks[Uuid::fromHexToBytes($first)]), Uuid::randomHex(), 3, ApprovalOutcome::Approved, 'First reviewer.', Context::createDefaultContext($source));
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString($policy === 'core:all' ? 'DECIDED:5' : 'CANCELLED', $writer->getOutput());
            $execution = $connection->fetchAssociative('SELECT status, outcome FROM approval_flow_execution WHERE id = :id', ['id' => $executionId]);
            static::assertSame(['status' => 'completed', 'outcome' => 'approved'], $execution);
            $statuses = $connection->fetchFirstColumn('SELECT status FROM approval_flow_task WHERE execution_id = :id ORDER BY status', ['id' => $executionId]);
            static::assertSame($policy === 'core:all' ? ['approved', 'approved'] : ['approved', 'cancelled'], $statuses);
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->executeStatement('DELETE FROM approval_flow_task WHERE execution_id = :id', ['id' => $executionId]);
            $connection->delete('approval_flow_execution', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_operation', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_instance', ['id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($releaseId)]);
            $connection->delete('approval_flow_draft', ['definition_id' => $definitionId]);
            $connection->delete('approval_flow_definition', ['id' => $definitionId]);
            foreach ([$first, $second] as $userId) {
                $key = ['id' => Uuid::fromHexToBytes($userId)];
                $avatar = $connection->fetchOne('SELECT avatar_id FROM `user` WHERE id = :id', $key);
                $connection->delete('user', $key);
                $connection->delete('media', ['id' => $avatar]);
            }
            $filesystem->remove($directory);
        }
    }

    public static function groupPolicies(): iterable
    {
        yield 'all keeps both committed votes' => ['core:all'];
        yield 'any rejects a late vote after cancellation' => ['core:any'];
    }

    #[DataProvider('progressionCommands')]
    public function testConcurrentProgressionUsesTheCommittedTailAndTerminalReceipt(bool $end, bool $sameCommand, bool $serial = false): void
    {
        $kernel = static::getContainer();
        $connection = $kernel->get(Connection::class);
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-concurrent-completion-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($filesystem, $directory);
        $runtime = ApprovalRuntimeFixture::container($kernel, $manifest);
        $userId = TestUser::createNewTestUser($connection)->getUserId();
        $graph = str_replace('"finance-user"', json_encode($userId, \JSON_THROW_ON_ERROR), GraphFixture::json());
        $releaseId = ApprovalRuntimeFixture::publish($kernel, $runtime, $graph);
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $instanceId = InstanceFixture::create($connection, $releaseId, ApprovalInstanceStatus::Running);
        $service = $runtime->get(ApprovalInstanceService::class);
        $context = Context::createDefaultContext();
        $service->enterStart($instanceId, Uuid::randomHex(), 1, $context);
        $execution = $connection->fetchOne('SELECT id FROM approval_flow_execution WHERE instance_id = :id AND `sequence` = 2', ['id' => Uuid::fromHexToBytes($instanceId)]);
        static::assertIsString($execution);
        $executionId = Uuid::fromBytesToHex($execution);
        $service->enterApproval($instanceId, $executionId, Uuid::randomHex(), 2, $context);
        $task = $connection->fetchOne('SELECT id FROM approval_flow_task WHERE execution_id = :id', ['id' => $execution]);
        static::assertIsString($task);
        $source = new AdminApiSource($userId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $service->decide($instanceId, Uuid::fromBytesToHex($task), Uuid::randomHex(), 3, ApprovalOutcome::Approved, '', Context::createDefaultContext($source));
        $version = 4;
        if ($end) {
            $service->advanceApproval($instanceId, $executionId, Uuid::randomHex(), 4, $context);
            $execution = $connection->fetchOne('SELECT id FROM approval_flow_execution WHERE instance_id = :id AND `sequence` = 3', ['id' => Uuid::fromHexToBytes($instanceId)]);
            static::assertIsString($execution);
            $executionId = Uuid::fromBytesToHex($execution);
            $version = 5;
        }
        $commandId = Uuid::randomHex();
        $input = new InputStream();
        $writer = new Process([
            \PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-progression.php',
            $instanceId, $executionId, $sameCommand ? $commandId : Uuid::randomHex(), $manifest, $serial ? 'progress' : ($end ? 'end' : 'advance'), (string) $version,
        ]);
        $writer->setInput($input);
        $writer->setTimeout(30);

        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT:running');
            $connection->beginTransaction();
            if ($serial) {
                $service->progress($instanceId, $executionId, $commandId, $version, $context);
            } elseif ($end) {
                $service->enterEnd($instanceId, $executionId, $commandId, $version, $context);
            } else {
                $service->advanceApproval($instanceId, $executionId, $commandId, $version, $context);
            }
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            $expected = $serial ? 'RECEIPT:completed:approved:5' : ($end ? 'RECEIPT:completed:approved:6' : 'RECEIPT:running:none:5');
            static::assertStringContainsString($sameCommand ? $expected : 'GUARDED', $writer->getOutput());
            static::assertSame(3, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
            static::assertSame($version, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $connection->executeStatement('DELETE task FROM approval_flow_task task INNER JOIN approval_flow_execution execution ON execution.id = task.execution_id WHERE execution.instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->executeStatement('DELETE delivery FROM approval_flow_outbox delivery INNER JOIN approval_flow_result result ON result.id = delivery.result_id WHERE result.instance_id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_result', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_execution', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_operation', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_instance', ['id' => Uuid::fromHexToBytes($instanceId)]);
            $connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($releaseId)]);
            $connection->delete('approval_flow_draft', ['definition_id' => $definitionId]);
            $connection->delete('approval_flow_definition', ['id' => $definitionId]);
            $key = ['id' => Uuid::fromHexToBytes($userId)];
            $avatar = $connection->fetchOne('SELECT avatar_id FROM `user` WHERE id = :id', $key);
            $connection->delete('user', $key);
            $connection->delete('media', ['id' => $avatar]);
            $filesystem->remove($directory);
        }
    }

    public static function progressionCommands(): iterable
    {
        yield 'concurrent serial progression replays its terminal receipt' => [false, true, true];
        yield 'another serial command cannot duplicate completion' => [false, false, true];
        yield 'concurrent advancement replays the committed receipt' => [false, true];
        yield 'a fresh command cannot duplicate the committed successor' => [false, false];
        yield 'concurrent end replays the committed outcome' => [true, true];
        yield 'a fresh command cannot reopen the completed instance' => [true, false];
    }

    public function testCommandRequiresTheAdmittedInstanceToBeVisibleInItsTransaction(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        $writer = DriverManager::getConnection($connection->getParams());
        $releaseId = InstanceFixture::publish($container);
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $isolation = $connection->getTransactionIsolation();
        $instanceId = null;

        try {
            $connection->setTransactionIsolation(TransactionIsolationLevel::REPEATABLE_READ);
            $connection->beginTransaction();
            // Establish the reader's snapshot before the other connection admits the instance.
            $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_instance');
            $instanceId = InstanceFixture::create($writer, $releaseId, ApprovalInstanceStatus::Running);
            $service = $container->get(ApprovalInstanceService::class);
            $commandId = Uuid::randomHex();
            $context = Context::createDefaultContext();
            $reason = 'Review required.';

            try {
                $service->suspend($instanceId, $commandId, 1, $reason, $context);
                static::fail('The command used an instance outside its transaction snapshot.');
            } catch (ApprovalFlowException $exception) {
                static::assertSame(ApprovalFlowException::INSTANCE_OUTSIDE_SNAPSHOT, $exception->getErrorCode());
            }

            $connection->rollBack();
            $receipt = $service->suspend($instanceId, $commandId, 1, $reason, $context);
            static::assertSame(ApprovalInstanceStatus::Suspended, $receipt->status);
            static::assertSame(2, $receipt->aggregateVersion);
        } finally {
            while ($connection->getTransactionNestingLevel() > 0) {
                $connection->rollBack();
            }
            $connection->setTransactionIsolation($isolation);
            if ($instanceId !== null) {
                $writer->delete('approval_flow_operation', ['instance_id' => Uuid::fromHexToBytes($instanceId)]);
                $writer->delete('approval_flow_instance', ['id' => Uuid::fromHexToBytes($instanceId)]);
            }
            $writer->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($releaseId)]);
            $writer->delete('approval_flow_draft', ['definition_id' => $definitionId]);
            $writer->delete('approval_flow_definition', ['id' => $definitionId]);
            $writer->close();
        }
    }

    private function waitFor(Process $process, string $message): void
    {
        $received = $process->waitUntil(static fn (): bool => str_contains($process->getOutput(), $message));
        static::assertTrue($received, $process->getOutput() . $process->getErrorOutput());
    }
}
