<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalProgressionRunner;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\ProgressApprovalFlowsTask;
use Shopwell\Core\Framework\ApprovalFlow\ScheduledTask\ProgressApprovalFlowsTaskHandler;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalProgressionRunnerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalInstanceService $service;

    private string $releaseId;

    private string $graphJson;

    private Context $reviewer;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-runner-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory), static function (ContainerBuilder $runtime): void {
            $runtime->getDefinition(ApprovalInstanceStore::class)->setPublic(true);
            $runtime->getDefinition(ProgressApprovalFlowsTaskHandler::class)->setPublic(true);
        });
        $this->service = $this->runtime->get(ApprovalInstanceService::class);
        $userId = TestUser::createNewTestUser($this->connection)->getUserId();
        $source = new AdminApiSource($userId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $this->reviewer = Context::createDefaultContext($source);
        $this->graphJson = str_replace('finance-user', $userId, GraphFixture::json());
        $this->releaseId = ApprovalRuntimeFixture::publish($kernel, $this->runtime, $this->graphJson);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testTaskIsDiscoveredByTheHostRegistry(): void
    {
        static::getContainer()->get(TaskRegistry::class)->registerTasks();
        $task = $this->connection->fetchAssociative('SELECT scheduled_task_class, run_interval FROM scheduled_task WHERE name = :name', ['name' => ProgressApprovalFlowsTask::getTaskName()]);

        static::assertNotFalse($task);
        static::assertSame(ProgressApprovalFlowsTask::class, $task['scheduled_task_class']);
        static::assertSame(60, (int) $task['run_interval']);
        static::assertTrue(ProgressApprovalFlowsTask::shouldRescheduleOnFailure());
    }

    public function testRegisteredTaskProgressesCommittedDecisionsAndIgnoresPendingReviews(): void
    {
        $ready = $this->instance(decided: true);
        $pending = $this->instance(decided: false);
        $handler = $this->runtime->get(ProgressApprovalFlowsTaskHandler::class);

        $handler->run();
        $handler->run();

        static::assertSame('completed', $this->instanceStatus($ready));
        static::assertSame('running', $this->instanceStatus($pending));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_outbox'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'pending']));
    }

    public function testOneFailedInstanceDoesNotBlockOtherReadyInstancesAndCanBeRetried(): void
    {
        $failed = $this->instance(decided: true);
        $failedReleaseId = $this->releaseId;
        $this->releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, $this->graphJson);
        $healthy = $this->instance(decided: true);
        $contract = $this->connection->fetchOne('SELECT contract_json FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($failedReleaseId)]);
        static::assertIsString($contract);
        // A broken release must not block another instance on a valid deployment.
        $this->connection->update('approval_flow_release', ['contract_json' => '{}'], ['id' => Uuid::fromHexToBytes($failedReleaseId)]);
        $errors = [];
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->willReturnCallback(static function (string $message, array $context) use (&$errors): void {
            $errors[] = $context;
        });
        $runner = new ApprovalProgressionRunner($this->runtime->get(ApprovalInstanceStore::class), $this->service, $logger);

        $runner->run();

        static::assertSame('running', $this->instanceStatus($failed));
        static::assertSame('completed', $this->instanceStatus($healthy));
        static::assertSame([$failed], array_column($errors, 'instanceId'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'approved']));
        $this->connection->update('approval_flow_release', ['contract_json' => $contract], ['id' => Uuid::fromHexToBytes($failedReleaseId)]);
        $this->runtime->get(ProgressApprovalFlowsTaskHandler::class)->run();
        static::assertSame('completed', $this->instanceStatus($failed));
        static::assertSame('completed', $this->instanceStatus($healthy));
    }

    public function testAFullPageOfFailuresDoesNotStarveTheNextPage(): void
    {
        $failedRelease = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, $this->graphJson);
        $this->connection->update('approval_flow_release', ['contract_json' => '{}'], ['id' => Uuid::fromHexToBytes($failedRelease)]);
        $prefix = substr(Uuid::randomHex(), 0, 24);
        $lastId = $prefix . \sprintf('%08x', 100);
        // Seed committed group outcomes directly to exercise pagination without repeating 101 voting scenarios.
        for ($index = 0; $index <= 100; ++$index) {
            $id = Uuid::fromHexToBytes($prefix . \sprintf('%08x', $index));
            $this->connection->insert('approval_flow_instance', [
                'id' => $id, 'release_id' => Uuid::fromHexToBytes($index === 100 ? $this->releaseId : $failedRelease),
                'status' => 'running', 'aggregate_version' => 4, 'created_at' => '2026-11-19 00:00:00.000',
            ]);
            $this->connection->insert('approval_flow_execution', [
                'id' => Uuid::fromHexToBytes(Uuid::randomHex()), 'instance_id' => $id, 'node_id' => 'review',
                'sequence' => 2, 'status' => 'completed', 'approval_policy' => 'core:single', 'outcome' => 'approved',
                'created_at' => '2026-11-19 00:00:00.000',
            ]);
        }
        $logger = static::createMock(LoggerInterface::class);
        $logger->expects($this->exactly(100))->method('error');
        $runner = new ApprovalProgressionRunner($this->runtime->get(ApprovalInstanceStore::class), $this->service, $logger);

        $runner->run();

        static::assertSame('completed', $this->instanceStatus($lastId));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
    }

    public function testSuspendedInstancesAreNotProgressed(): void
    {
        $id = $this->instance(decided: true);
        $this->service->suspend($id, Uuid::randomHex(), 4, 'Manual review.', Context::createDefaultContext());

        $this->runtime->get(ProgressApprovalFlowsTaskHandler::class)->run();

        static::assertSame('suspended', $this->instanceStatus($id));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
    }

    private function instance(bool $decided): string
    {
        $id = InstanceFixture::create($this->connection, $this->releaseId, ApprovalInstanceStatus::Running);
        $context = Context::createDefaultContext();
        $this->service->enterStart($id, Uuid::randomHex(), 1, $context);
        $execution = $this->connection->fetchOne('SELECT id FROM approval_flow_execution WHERE instance_id = :id AND node_id = :node', ['id' => Uuid::fromHexToBytes($id), 'node' => 'review']);
        static::assertIsString($execution);
        $this->service->enterApproval($id, Uuid::fromBytesToHex($execution), Uuid::randomHex(), 2, $context);
        if ($decided) {
            $task = $this->connection->fetchOne('SELECT id FROM approval_flow_task WHERE execution_id = :id', ['id' => $execution]);
            static::assertIsString($task);
            $this->service->decide($id, Uuid::fromBytesToHex($task), Uuid::randomHex(), 3, ApprovalOutcome::Approved, '', $this->reviewer);
        }

        return $id;
    }

    private function instanceStatus(string $instanceId): string
    {
        $status = $this->connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]);
        static::assertIsString($status);

        return $status;
    }
}
