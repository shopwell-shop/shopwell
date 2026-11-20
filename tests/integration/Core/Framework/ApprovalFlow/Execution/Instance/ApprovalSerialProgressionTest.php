<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\ApprovalRuntimeFixture;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class ApprovalSerialProgressionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalInstanceService $service;

    private string $reviewerId;

    private Context $reviewer;

    private string $instanceId;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-serial-' . Uuid::randomHex();
        $this->runtime = ApprovalRuntimeFixture::container($kernel, PublicationFixture::writeManifest($this->filesystem, $this->directory));
        $this->service = $this->runtime->get(ApprovalInstanceService::class);
        $this->reviewerId = TestUser::createNewTestUser($this->connection)->getUserId();
        $source = new AdminApiSource($this->reviewerId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $this->reviewer = Context::createDefaultContext($source);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testProgressionEntersTheEndAndRecordsOneResult(): void
    {
        $this->publish($this->graph());
        $executionId = $this->decide(ApprovalOutcome::Approved);
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $receipt = $this->service->progress($this->instanceId, $executionId, $commandId, 4, $context);

        static::assertSame(ApprovalInstanceStatus::Completed, $receipt->status);
        static::assertSame(ApprovalOutcome::Approved, $receipt->outcome);
        static::assertSame(5, $receipt->aggregateVersion);
        static::assertSame(['start', 'review', 'end'], $this->connection->fetchFirstColumn('SELECT node_id FROM approval_flow_execution ORDER BY `sequence`'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution WHERE status <> :status', ['status' => 'completed']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_outbox'));
        $this->filesystem->remove($this->directory);
        static::assertEquals($receipt, $this->service->progress($this->instanceId, $executionId, $commandId, 4, $context));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
    }

    public function testProgressionCreatesTheNextApprovalTasks(): void
    {
        $this->publish($this->twoLevels());
        $executionId = $this->decide(ApprovalOutcome::Approved);
        $commandId = Uuid::randomHex();

        $receipt = $this->service->progress($this->instanceId, $executionId, $commandId, 4, Context::createDefaultContext());

        static::assertSame(ApprovalInstanceStatus::Running, $receipt->status);
        static::assertSame('awaiting_approval', $this->connection->fetchOne('SELECT status FROM approval_flow_execution WHERE node_id = :id', ['id' => 'second-review']));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE status = :status', ['status' => 'pending']));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        static::assertEquals($receipt, $this->service->progress($this->instanceId, $executionId, $commandId, 4, Context::createDefaultContext()));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task'));
    }

    public function testRejectedApprovalCompletesWithoutEnteringTheApprovedRoute(): void
    {
        $this->publish($this->twoLevels());
        $executionId = $this->decide(ApprovalOutcome::Rejected);
        $this->filesystem->remove($this->directory);

        $receipt = $this->service->progress($this->instanceId, $executionId, Uuid::randomHex(), 4, Context::createDefaultContext());

        static::assertSame(ApprovalOutcome::Rejected, $receipt->outcome);
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::assertSame('rejected', $this->connection->fetchOne('SELECT outcome FROM approval_flow_result'));
    }

    public function testUnavailableNextReviewerDoesNotUndoTheCommittedVote(): void
    {
        $this->publish($this->twoLevels());
        $executionId = $this->decide(ApprovalOutcome::Approved);
        $commandId = Uuid::randomHex();
        static::getContainer()->get('user.repository')->update([['id' => $this->reviewerId, 'active' => false]], Context::createDefaultContext());
        try {
            $this->service->progress($this->instanceId, $executionId, $commandId, 4, Context::createDefaultContext());
            static::fail('The next review must not silently lose its assignee.');
        } catch (ApprovalFlowException $exception) {
            static::assertSame(ApprovalFlowException::ASSIGNEE_UNAVAILABLE, $exception->getErrorCode());
        }
        static::assertSame('approved', $this->connection->fetchOne('SELECT status FROM approval_flow_task'));
        static::assertSame(4, (int) $this->connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::getContainer()->get('user.repository')->update([['id' => $this->reviewerId, 'active' => true]], Context::createDefaultContext());
        static::assertSame(5, $this->service->progress($this->instanceId, $executionId, $commandId, 4, Context::createDefaultContext())->aggregateVersion);
    }

    public function testResultWriteFailureRollsBackProgressionButPreservesTheVote(): void
    {
        $this->publish($this->graph());
        $executionId = $this->decide(ApprovalOutcome::Approved);
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_result.written', static function (): void {
            throw new \RuntimeException('Result write failed.');
        }, once: true);
        $commandId = Uuid::randomHex();
        try {
            $this->service->progress($this->instanceId, $executionId, $commandId, 4, Context::createDefaultContext());
            static::fail('The terminal route must roll back with its result.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Result write failed.', $exception->getMessage());
        }
        static::assertSame('approved', $this->connection->fetchOne('SELECT status FROM approval_flow_task'));
        static::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_execution'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result'));
        static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_outbox'));
        static::assertSame(ApprovalOutcome::Approved, $this->service->progress($this->instanceId, $executionId, $commandId, 4, Context::createDefaultContext())->outcome);
    }

    public function testProgressionUsesTheLockedVersionCheck(): void
    {
        $this->publish($this->graph());
        $executionId = $this->decide(ApprovalOutcome::Approved);
        $this->expectExceptionObject(ApprovalFlowException::instanceVersionConflict($this->instanceId, 0, 4));

        $this->service->progress($this->instanceId, $executionId, Uuid::randomHex(), 0, Context::createDefaultContext());
    }

    public function testInternalProgressionRetainsTheCallerContext(): void
    {
        $this->publish($this->graph());
        $executionId = $this->decide(ApprovalOutcome::Approved);
        $commandId = Uuid::randomHex();

        $receipt = $this->service->progress($this->instanceId, $executionId, $commandId, 4, $this->reviewer);

        static::assertSame(ApprovalOutcome::Approved, $receipt->outcome);
        $actor = $this->connection->fetchOne('SELECT actor_json FROM approval_flow_operation WHERE command_id = :id', ['id' => Uuid::fromHexToBytes($commandId)]);
        static::assertIsString($actor);
        static::assertSame(['source' => 'admin-api', 'userId' => $this->reviewerId, 'integrationId' => null], json_decode($actor, associative: true, flags: \JSON_THROW_ON_ERROR));
        static::assertSame(Context::USER_SCOPE, $this->reviewer->getScope());
    }

    /**
     * @return GraphPayload
     */
    private function graph(): \stdClass
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(str_replace('finance-user', $this->reviewerId, GraphFixture::json()), flags: \JSON_THROW_ON_ERROR);

        return $graph;
    }

    /**
     * @return GraphPayload
     */
    private function twoLevels(): \stdClass
    {
        $graph = $this->graph();
        $next = clone $graph->nodes[1];
        $next->id = 'second-review';
        $graph->nodes[] = $next;
        $graph->connections[1]->id = 'review-second';
        $graph->connections[1]->target = 'second-review';
        $edge = clone $graph->connections[1];
        $edge->id = 'second-end';
        $edge->source = 'second-review';
        $edge->target = 'end';
        $graph->connections[] = $edge;

        return $graph;
    }

    /**
     * @param GraphPayload $graph
     */
    private function publish(\stdClass $graph): void
    {
        $releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, json_encode($graph, \JSON_THROW_ON_ERROR));
        $this->instanceId = InstanceFixture::create($this->connection, $releaseId, ApprovalInstanceStatus::Running);
        $context = Context::createDefaultContext();
        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, $context);
        $executionId = $this->connection->fetchOne('SELECT id FROM approval_flow_execution WHERE instance_id = :id AND node_id = :node', ['id' => Uuid::fromHexToBytes($this->instanceId), 'node' => 'review']);
        static::assertIsString($executionId);
        $this->service->enterApproval($this->instanceId, Uuid::fromBytesToHex($executionId), Uuid::randomHex(), 2, $context);
    }

    private function decide(ApprovalOutcome $outcome): string
    {
        $task = $this->connection->fetchAssociative('SELECT id, execution_id FROM approval_flow_task WHERE status = :status', ['status' => 'pending']);
        static::assertNotFalse($task);
        $this->service->decide($this->instanceId, Uuid::fromBytesToHex($task['id']), Uuid::randomHex(), 3, $outcome, 'Checked.', $this->reviewer);

        return Uuid::fromBytesToHex($task['execution_id']);
    }
}
