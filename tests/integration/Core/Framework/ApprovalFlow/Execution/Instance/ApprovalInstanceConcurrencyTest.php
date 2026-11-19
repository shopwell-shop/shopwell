<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
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
