<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
class ApprovalCompletionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private Filesystem $filesystem;

    private string $directory;

    private ContainerBuilder $runtime;

    private ApprovalInstanceService $service;

    private string $userId;

    private Context $reviewer;

    private string $releaseId;

    private string $instanceId;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-completion-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $this->runtime = ApprovalRuntimeFixture::container($kernel, $manifest);
        $this->service = $this->runtime->get(ApprovalInstanceService::class);
        $this->userId = TestUser::createNewTestUser($this->connection)->getUserId();
        $source = new AdminApiSource($this->userId);
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $this->reviewer = Context::createDefaultContext($source);
        $this->publish(str_replace('"finance-user"', json_encode($this->userId, \JSON_THROW_ON_ERROR), GraphFixture::json()));
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testApprovalQueuesItsSuccessorAndTheEndCommitsTheProcessOutcome(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $advanceCommand = Uuid::randomHex();

        $advanced = $this->service->advanceApproval($this->instanceId, $executionId, $advanceCommand, 4, $context);

        static::assertSame(ApprovalInstanceStatus::Running, $advanced->status);
        static::assertSame(5, $advanced->aggregateVersion);
        static::assertNull($advanced->outcome);
        $rows = $this->executions();
        static::assertSame(['start', 'review', 'end'], array_column($rows, 'node_id'));
        static::assertSame(['completed', 'completed', 'waiting'], array_column($rows, 'status'));
        static::assertSame([1, 2, 3], array_map(static fn (array $row): int => (int) $row['sequence'], $rows));
        static::assertSame('review-end', $rows[2]['incoming_connection_id']);
        $endId = Uuid::fromBytesToHex($rows[2]['id']);
        $endCommand = Uuid::randomHex();

        $completed = $this->service->enterEnd($this->instanceId, $endId, $endCommand, 5, $context);

        static::assertSame(ApprovalInstanceStatus::Completed, $completed->status);
        static::assertSame(ApprovalOutcome::Approved, $completed->outcome);
        static::assertSame(6, $completed->aggregateVersion);
        static::assertSame(['completed', 'completed', 'completed'], array_column($this->executions(), 'status'));
        static::assertSame('approved', $this->executions()[2]['outcome']);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_instance');
        $instance = $repository->search(new Criteria([$this->instanceId]), $context)->first();
        static::assertInstanceOf(ApprovalInstanceEntity::class, $instance);
        static::assertSame(ApprovalOutcome::Approved, $instance->getOutcome());
        $this->filesystem->remove($this->directory);
        static::assertEquals($completed, $this->service->enterEnd($this->instanceId, $endId, $endCommand, 5, $context));
        static::assertEquals($advanced, $this->service->advanceApproval($this->instanceId, $executionId, $advanceCommand, 4, $context));
        static::assertSame(5, $this->receiptCount());
        $result = $this->connection->fetchAssociative('SELECT id, release_id, command_id, aggregate_version, outcome FROM approval_flow_result WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]);
        static::assertNotFalse($result);
        static::assertSame(Uuid::fromHexToBytes($this->releaseId), $result['release_id']);
        static::assertSame(Uuid::fromHexToBytes($endCommand), $result['command_id']);
        static::assertSame(6, (int) $result['aggregate_version']);
        static::assertSame('approved', $result['outcome']);
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        static::assertSame('pending', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox WHERE result_id = :id', ['id' => $result['id']]));
    }

    public function testVetoCompletesAsRejectedWithoutEnteringTheApprovedEnd(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Rejected);
        $command = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->filesystem->remove($this->directory);

        $receipt = $this->service->advanceApproval($this->instanceId, $executionId, $command, 4, $context);

        static::assertSame(ApprovalInstanceStatus::Completed, $receipt->status);
        static::assertSame(ApprovalOutcome::Rejected, $receipt->outcome);
        static::assertSame(['start', 'review'], array_column($this->executions(), 'node_id'));
        static::assertSame('rejected', $this->connection->fetchOne('SELECT outcome FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        static::assertEquals($receipt, $this->service->advanceApproval($this->instanceId, $executionId, $command, 4, $context));
        static::assertSame('rejected', $this->connection->fetchOne('SELECT outcome FROM approval_flow_result WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'completed', 'advance-approval'));

        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 5, $context);
    }

    public function testSerialApprovalLevelsKeepIndependentExecutionsAndTasks(): void
    {
        $graph = $this->graph();
        $second = clone $graph->nodes[1];
        $second->id = 'second-review';
        $graph->nodes[] = $second;
        $graph->connections[1]->id = 'review-second';
        $graph->connections[1]->target = $second->id;
        $next = clone $graph->connections[1];
        $next->id = 'second-end';
        $next->source = $second->id;
        $next->target = 'end';
        $graph->connections[] = $next;
        $this->publish(json_encode($graph, \JSON_THROW_ON_ERROR));
        $firstId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();

        $this->service->advanceApproval($this->instanceId, $firstId, Uuid::randomHex(), 4, $context);

        $secondId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $this->service->enterApproval($this->instanceId, $secondId, Uuid::randomHex(), 5, $context);
        $task = $this->connection->fetchAssociative('SELECT id, status FROM approval_flow_task WHERE execution_id = :id', ['id' => Uuid::fromHexToBytes($secondId)]);
        static::assertNotFalse($task);
        static::assertSame('pending', $task['status']);
        $this->service->decide($this->instanceId, Uuid::fromBytesToHex($task['id']), Uuid::randomHex(), 6, ApprovalOutcome::Approved, '', $this->reviewer);
        $this->service->advanceApproval($this->instanceId, $secondId, Uuid::randomHex(), 7, $context);
        $endId = Uuid::fromBytesToHex($this->executions()[3]['id']);
        $receipt = $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 8, $context);

        static::assertSame(ApprovalOutcome::Approved, $receipt->outcome);
        static::assertSame(9, $receipt->aggregateVersion);
        static::assertSame(['start', 'review', 'second-review', 'end'], array_column($this->executions(), 'node_id'));
        static::assertSame([null, 'start-review', 'review-second', 'second-end'], array_column($this->executions(), 'incoming_connection_id'));
    }

    public function testTheEndUsesItsPublishedRejectedOutcome(): void
    {
        $graph = $this->graph();
        $graph->nodes[2]->config->outcome = 'rejected';
        $this->publish(json_encode($graph, \JSON_THROW_ON_ERROR));
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);

        $receipt = $this->service->enterEnd($this->instanceId, Uuid::fromBytesToHex($this->executions()[2]['id']), Uuid::randomHex(), 5, $context);

        static::assertSame(ApprovalOutcome::Rejected, $receipt->outcome);
        static::assertSame('rejected', $this->executions()[2]['outcome']);
    }

    public function testAnApprovalWithMultipleSuccessorsCannotBeExpandedYet(): void
    {
        $graph = $this->graph();
        $graph->nodes[1]->ports[1]->cardinality = 'many';
        $graph->nodes[2]->ports[0]->cardinality = 'many';
        $second = clone $graph->nodes[1];
        $second->id = 'second-review';
        $graph->nodes[] = $second;
        $branch = clone $graph->connections[1];
        $branch->id = 'review-second';
        $branch->target = $second->id;
        $graph->connections[] = $branch;
        $join = clone $graph->connections[1];
        $join->id = 'second-end';
        $join->source = $second->id;
        $graph->connections[] = $join;
        $this->publish(json_encode($graph, \JSON_THROW_ON_ERROR));
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $this->expectExceptionObject(ApprovalFlowException::unsupportedNodeRoute($this->releaseId, 'review'));

        try {
            $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, Context::createDefaultContext());
        } finally {
            static::assertCount(2, $this->executions());
            static::assertSame(3, $this->receiptCount());
        }
    }

    public function testACommandIdentityCannotChangeItsExecution(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, $commandId, 4, $context);
        $this->expectExceptionObject(ApprovalFlowException::instanceCommandConflict($this->instanceId, $commandId));

        $this->service->advanceApproval($this->instanceId, Uuid::fromBytesToHex($this->executions()[2]['id']), $commandId, 4, $context);
    }

    public function testAStaleVersionCannotAdvanceTheCompletedApproval(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $this->expectExceptionObject(ApprovalFlowException::instanceVersionConflict($this->instanceId, 3, 4));

        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 3, Context::createDefaultContext());
    }

    public function testAnotherCommandCannotExpandAnApprovalThatAlreadyHasASuccessor(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        $this->expectException(ApprovalFlowException::class);

        try {
            $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 5, $context);
        } finally {
            static::assertCount(3, $this->executions());
            static::assertSame(4, $this->receiptCount());
        }
    }

    public function testAnApprovalStillAwaitingItsVoteCannotAdvance(): void
    {
        $executionId = $this->openApproval();
        $this->expectException(ApprovalFlowException::class);

        try {
            $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 3, Context::createDefaultContext());
        } finally {
            static::assertCount(2, $this->executions());
            static::assertSame(2, $this->receiptCount());
        }
    }

    public function testAWaitingApprovalCannotBeEnteredAsAnEnd(): void
    {
        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());
        $executionId = Uuid::fromBytesToHex($this->executions()[1]['id']);
        $this->expectException(ApprovalFlowException::class);

        try {
            $this->service->enterEnd($this->instanceId, $executionId, Uuid::randomHex(), 2, Context::createDefaultContext());
        } finally {
            static::assertSame('waiting', $this->executions()[1]['status']);
            static::assertSame(1, $this->receiptCount());
        }
    }

    #[DataProvider('systemCommands')]
    public function testAdministrationPrivilegesCannotRunSystemProgression(bool $end): void
    {
        $source = new AdminApiSource($this->userId);
        $source->setIsAdmin(true);
        $this->expectException(MissingPrivilegeException::class);

        if ($end) {
            $this->service->enterEnd('invalid', 'invalid', 'invalid', 0, Context::createDefaultContext($source));
        } else {
            $this->service->advanceApproval('invalid', 'invalid', 'invalid', 0, Context::createDefaultContext($source));
        }
    }

    #[DataProvider('systemCommands')]
    public function testInvalidExecutionIdentityIsRejected(bool $end): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidExecutionId('invalid'));

        if ($end) {
            $this->service->enterEnd($this->instanceId, 'invalid', Uuid::randomHex(), 1, Context::createDefaultContext());
        } else {
            $this->service->advanceApproval($this->instanceId, 'invalid', Uuid::randomHex(), 1, Context::createDefaultContext());
        }
    }

    public static function systemCommands(): iterable
    {
        yield 'advance approval' => [false];
        yield 'enter end' => [true];
    }

    public function testAnExecutionCannotAdvanceOnAnotherInstance(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $other = InstanceFixture::create($this->connection, $this->releaseId, ApprovalInstanceStatus::Running);
        $this->expectExceptionObject(ApprovalFlowException::executionNotFound($executionId, $other));

        $this->service->advanceApproval($other, $executionId, Uuid::randomHex(), 1, Context::createDefaultContext());
    }

    public function testSuspensionStopsNewProgression(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->suspend($this->instanceId, Uuid::randomHex(), 4, 'Review required.', $context);
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'suspended', 'advance-approval'));

        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 5, $context);
    }

    public function testCorruptedReleaseCannotProduceASuccessor(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $this->connection->update('approval_flow_release', ['contract_json' => '{}'], ['id' => Uuid::fromHexToBytes($this->releaseId)]);
        $this->expectExceptionObject(ApprovalFlowException::releaseVerificationFailed($this->releaseId, 'contractJson'));

        try {
            $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, Context::createDefaultContext());
        } finally {
            static::assertCount(2, $this->executions());
            static::assertSame(3, $this->receiptCount());
        }
    }

    public function testEndCannotCompleteWhileAPendingTaskRemains(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        // Seed an inconsistent runtime fact to prove completion checks actual tasks, not only execution statuses.
        $this->connection->update('approval_flow_task', ['status' => 'pending'], ['execution_id' => Uuid::fromHexToBytes($executionId)]);
        $endId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $this->expectException(ApprovalFlowException::class);

        try {
            $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 5, $context);
        } finally {
            static::assertSame('waiting', $this->executions()[2]['status']);
            static::assertNull($this->connection->fetchOne('SELECT outcome FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public function testEndCannotCompleteWhileAnotherExecutionIsActive(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        $this->connection->update('approval_flow_execution', ['status' => 'awaiting_approval'], ['id' => Uuid::fromHexToBytes($executionId)]);
        $endId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeProgression($endId, 'Other node executions are still active.'));

        $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 5, $context);
    }

    public function testVetoCannotFinishAnInstanceWithUnsettledTasks(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Rejected);
        $this->connection->update('approval_flow_task', ['status' => 'pending'], ['execution_id' => Uuid::fromHexToBytes($executionId)]);
        $this->expectExceptionObject(ApprovalFlowException::invalidNodeProgression($executionId, 'Approval tasks are still pending.'));

        try {
            $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, Context::createDefaultContext());
        } finally {
            static::assertSame(3, $this->receiptCount());
            static::assertSame('running', $this->connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public function testSuspensionStopsTheWaitingEnd(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        $endId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $this->service->suspend($this->instanceId, Uuid::randomHex(), 5, 'Pause before completion.', $context);
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'suspended', 'enter-end'));

        $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 6, $context);
    }

    public function testACompletedInstanceCannotBeEndedAgainWithANewCommand(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        $endId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 5, $context);
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'completed', 'enter-end'));

        $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 6, $context);
    }

    #[DataProvider('failureStages')]
    public function testReceiptFailureRollsBackTheWholeProgression(string $stage): void
    {
        $executionId = $this->completeApproval($stage === 'veto' ? ApprovalOutcome::Rejected : ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $version = 4;
        if ($stage === 'end') {
            $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), $version, $context);
            $executionId = Uuid::fromBytesToHex($this->executions()[2]['id']);
            $version = 5;
        }
        $before = $this->executions();
        $failure = new \RuntimeException('Completion receipt failed.');
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_operation.written', static function () use ($failure): void {
            throw $failure;
        });
        $this->expectExceptionObject($failure);

        try {
            if ($stage === 'end') {
                $this->service->enterEnd($this->instanceId, $executionId, Uuid::randomHex(), $version, $context);
            } else {
                $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), $version, $context);
            }
        } finally {
            static::assertSame($before, $this->executions());
            static::assertSame($version - 1, $this->receiptCount());
            $instance = $this->connection->fetchAssociative('SELECT status, outcome, aggregate_version FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]);
            static::assertNotFalse($instance);
            static::assertSame('running', $instance['status']);
            static::assertNull($instance['outcome']);
            static::assertSame($version, (int) $instance['aggregate_version']);
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public function testResultWriteFailureRollsBackTheEndAndItsImmutableResult(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        $endId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $failure = new \RuntimeException('Result persistence failed.');
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_result.written', static function () use ($failure): void {
            throw $failure;
        });
        $this->expectExceptionObject($failure);

        try {
            $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 5, $context);
        } finally {
            static::assertSame('waiting', $this->executions()[2]['status']);
            static::assertSame(4, $this->receiptCount());
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public function testOutboxInsertFailureRollsBackTheTerminalCommand(): void
    {
        $executionId = $this->completeApproval(ApprovalOutcome::Approved);
        $context = Context::createDefaultContext();
        $this->service->advanceApproval($this->instanceId, $executionId, Uuid::randomHex(), 4, $context);
        $endId = Uuid::fromBytesToHex($this->executions()[2]['id']);
        $connection = $this->connection;
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_result.written', static function (EntityWrittenEvent $event) use ($connection): void {
            $id = $event->getIds()[0];
            // A fixture listener takes the work key, forcing the following outbox insertion to fail.
            $connection->insert('approval_flow_outbox', [
                'result_id' => Uuid::fromHexToBytes($id), 'status' => 'pending', 'attempt' => 0,
                'next_attempt_at' => '2026-11-20 00:00:00.000', 'created_at' => '2026-11-20 00:00:00.000',
            ]);
        });
        $this->expectException(UniqueConstraintViolationException::class);

        try {
            $this->service->enterEnd($this->instanceId, $endId, Uuid::randomHex(), 5, $context);
        } finally {
            static::assertSame('waiting', $this->executions()[2]['status']);
            static::assertSame(4, $this->receiptCount());
            static::assertSame(0, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_result WHERE instance_id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
            static::assertSame('running', $this->connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public static function failureStages(): iterable
    {
        yield 'successor creation' => ['advance'];
        yield 'veto completion' => ['veto'];
        yield 'end completion' => ['end'];
    }

    private function publish(string $graph): void
    {
        $this->releaseId = ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, $graph);
        $this->instanceId = InstanceFixture::create($this->connection, $this->releaseId, ApprovalInstanceStatus::Running);
    }

    /**
     * @return GraphPayload
     */
    private function graph(): \stdClass
    {
        /** @var GraphPayload $graph */
        $graph = json_decode(str_replace('"finance-user"', json_encode($this->userId, \JSON_THROW_ON_ERROR), GraphFixture::json()), flags: \JSON_THROW_ON_ERROR);

        return $graph;
    }

    private function openApproval(): string
    {
        $context = Context::createDefaultContext();
        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, $context);
        $executionId = Uuid::fromBytesToHex($this->executions()[1]['id']);
        $this->service->enterApproval($this->instanceId, $executionId, Uuid::randomHex(), 2, $context);

        return $executionId;
    }

    private function completeApproval(ApprovalOutcome $outcome): string
    {
        $executionId = $this->openApproval();
        $taskId = $this->connection->fetchOne('SELECT id FROM approval_flow_task WHERE execution_id = :id', ['id' => Uuid::fromHexToBytes($executionId)]);
        static::assertIsString($taskId);
        $this->service->decide($this->instanceId, Uuid::fromBytesToHex($taskId), Uuid::randomHex(), 3, $outcome, '', $this->reviewer);

        return $executionId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function executions(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM approval_flow_execution WHERE instance_id = :instance ORDER BY `sequence`', ['instance' => Uuid::fromHexToBytes($this->instanceId)]);
    }

    private function receiptCount(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_operation WHERE instance_id = :instance', ['instance' => Uuid::fromHexToBytes($this->instanceId)]);
    }
}
