<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ParallelApprovalFixture;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalParallelConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('competingOperations')]
    public function testACompetingBranchUsesCurrentScopeFactsAfterWaiting(bool $lateVote): void
    {
        $kernel = static::getContainer();
        $connection = $kernel->get(Connection::class);
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/approval-parallel-race-' . Uuid::randomHex();
        $manifest = ParallelApprovalFixture::manifest($filesystem, $directory);
        $runtime = ParallelApprovalFixture::container($kernel, $manifest);
        $userId = TestUser::createNewTestUser($connection)->getUserId();
        $releaseId = ApprovalRuntimeFixture::publish($runtime, $runtime, ParallelApprovalFixture::graph($userId, minimum: $lateVote ? 1 : 0));
        $definitionId = $connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($releaseId)]);
        $instanceId = InstanceFixture::create($connection, $releaseId, ApprovalInstanceStatus::Draft);
        $service = $runtime->get(ApprovalInstanceService::class);
        $runtime->get(ApprovalInstanceStore::class)->start($instanceId, Uuid::randomHex(), 1, '{"source":"system"}', Context::createDefaultContext());
        $source = new AdminApiSource($userId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $reviewer = Context::createDefaultContext($source);
        $tasks = $connection->fetchAllAssociative('SELECT task.id, task.execution_id, execution.node_id FROM approval_flow_task task INNER JOIN approval_flow_execution execution ON execution.id = task.execution_id WHERE execution.instance_id = :instance ORDER BY execution.node_id', ['instance' => Uuid::fromHexToBytes($instanceId)]);
        static::assertCount(2, $tasks);
        $finance = Uuid::fromBytesToHex($tasks[0]['execution_id']);
        $manager = Uuid::fromBytesToHex($tasks[1]['execution_id']);
        if (!$lateVote) {
            $service->decide($instanceId, Uuid::fromBytesToHex($tasks[0]['id']), Uuid::randomHex(), 2, ApprovalOutcome::Approved, '', $reviewer);
            $service->decide($instanceId, Uuid::fromBytesToHex($tasks[1]['id']), Uuid::randomHex(), 3, ApprovalOutcome::Approved, '', $reviewer);
        }
        $input = new InputStream();
        $writer = new Process([
            \PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__ . '/_fixtures/competing-parallel.php',
            $instanceId, $manager, Uuid::fromBytesToHex($tasks[1]['id']), $userId, Uuid::randomHex(), $manifest,
            $lateVote ? 'decide' : 'progress', $lateVote ? '4' : '5',
        ]);
        $writer->setInput($input);
        $writer->setTimeout(30);
        try {
            $writer->start();
            $this->waitFor($writer, 'SNAPSHOT');
            $connection->beginTransaction();
            if ($lateVote) {
                $service->decide($instanceId, Uuid::fromBytesToHex($tasks[0]['id']), Uuid::randomHex(), 2, ApprovalOutcome::Approved, '', $reviewer);
            }
            $service->progress($instanceId, $finance, Uuid::randomHex(), $lateVote ? 3 : 4, Context::createDefaultContext());
            $input->write("compete\n");
            $this->waitFor($writer, 'BLOCKED');
            $connection->commit();
            $input->write("retry\n");
            $input->close();

            static::assertSame(0, $writer->wait(), $writer->getErrorOutput());
            static::assertStringContainsString($lateVote ? 'CLOSED' : 'FINISHED:approved:6', $writer->getOutput());
            static::assertSame('completed', $connection->fetchOne('SELECT status FROM approval_flow_execution_scope WHERE instance_id = :instance', ['instance' => Uuid::fromHexToBytes($instanceId)]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result WHERE instance_id = :instance', ['instance' => Uuid::fromHexToBytes($instanceId)]));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution WHERE instance_id = :instance AND node_id = :node', ['instance' => Uuid::fromHexToBytes($instanceId), 'node' => 'join']));
        } finally {
            $input->close();
            $writer->stop();
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $parameters = ['instance' => Uuid::fromHexToBytes($instanceId)];
            $connection->executeStatement('DELETE task FROM approval_flow_task task INNER JOIN approval_flow_execution e ON e.id = task.execution_id WHERE e.instance_id = :instance', $parameters);
            $connection->executeStatement('DELETE delivery FROM approval_flow_outbox delivery INNER JOIN approval_flow_result result ON result.id = delivery.result_id WHERE result.instance_id = :instance', $parameters);
            $connection->delete('approval_flow_result', ['instance_id' => $parameters['instance']]);
            $connection->delete('approval_flow_execution', ['instance_id' => $parameters['instance']]);
            $connection->executeStatement('DELETE branch FROM approval_flow_execution_branch branch INNER JOIN approval_flow_execution_scope scope ON scope.id = branch.scope_id WHERE scope.instance_id = :instance', $parameters);
            $connection->delete('approval_flow_execution_scope', ['instance_id' => $parameters['instance']]);
            $connection->delete('approval_flow_operation', ['instance_id' => $parameters['instance']]);
            $connection->delete('approval_flow_instance', ['id' => $parameters['instance']]);
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

    public static function competingOperations(): iterable
    {
        yield 'second arrival sees the first committed arrival despite its older snapshot' => [false];
        yield 'a late vote cannot escape the committed early-join cancellation' => [true];
    }

    private function waitFor(Process $writer, string $message): void
    {
        static::assertTrue($writer->waitUntil(static fn (): bool => str_contains($writer->getOutput(), $message)), $writer->getOutput() . $writer->getErrorOutput());
    }
}
