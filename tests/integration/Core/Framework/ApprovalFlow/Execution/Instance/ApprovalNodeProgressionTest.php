<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Task\ApprovalTaskEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
 * @internal
 */
#[Package('framework')]
class ApprovalNodeProgressionTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Filesystem $filesystem;

    private string $directory;

    private Connection $connection;

    private ContainerBuilder $runtime;

    private ApprovalInstanceService $service;

    private string $instanceId;

    private string $releaseId;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/approval-progression-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $runtime = $this->runtime = ApprovalRuntimeFixture::container($kernel, $manifest);

        $this->service = $runtime->get(ApprovalInstanceService::class);
        $this->releaseId = $this->publish(GraphFixture::json());
        $this->instanceId = InstanceFixture::create($this->connection, $this->releaseId, ApprovalInstanceStatus::Running);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testEnteringStartRecordsCompletionAndQueuesItsPublishedSuccessor(): void
    {
        $context = Context::createDefaultContext();
        $commandId = Uuid::randomHex();

        $receipt = $this->service->enterStart($this->instanceId, $commandId, 1, $context);

        static::assertSame(ApprovalInstanceStatus::Running, $receipt->status);
        static::assertSame(2, $receipt->aggregateVersion);
        $rows = $this->executions();
        static::assertSame(['start', 'review'], array_column($rows, 'node_id'));
        static::assertSame(['completed', 'waiting'], array_column($rows, 'status'));
        static::assertSame([null, 'start-review'], array_column($rows, 'incoming_connection_id'));
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_execution');
        $criteria = (new Criteria([Uuid::fromBytesToHex($rows[1]['id'])]))->addAssociation('instance.release');
        $waiting = $repository->search($criteria, $context)->first();
        static::assertInstanceOf(ApprovalNodeExecutionEntity::class, $waiting);
        static::assertSame(ApprovalNodeExecutionStatus::Waiting, $waiting->getStatus());
        static::assertSame($this->releaseId, $waiting->getInstance()?->getRelease()?->getId());

        // Return the original receipt even after suspension or artifacts becoming unavailable.
        $this->service->suspend($this->instanceId, Uuid::randomHex(), 2, 'Pause after entering start.', $context);
        $this->filesystem->remove($this->directory);
        static::assertEquals($receipt, $this->service->enterStart($this->instanceId, $commandId, 1, $context));
        static::assertSame($rows, $this->executions());
        static::assertSame(2, $this->receiptCount());
    }

    public function testAnotherCommandCannotEnterStartAgainAtTheCurrentVersion(): void
    {
        $context = Context::createDefaultContext();
        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, $context);
        $this->expectExceptionObject(ApprovalFlowException::startNodeAlreadyEntered($this->instanceId));

        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 2, $context);
    }

    public function testEnteringApprovalCreatesOneTaskPerPublishedAssignee(): void
    {
        $assignees = [$this->createUser(active: true), $this->createUser(active: true)];
        $executionId = $this->queueApproval($assignees);
        $commandId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $receipt = $this->service->enterApproval($this->instanceId, $executionId, $commandId, 2, $context);

        static::assertSame(3, $receipt->aggregateVersion);
        static::assertSame(['completed', 'awaiting_approval'], array_column($this->executions(), 'status'));
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_task');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('executionId', $executionId), new EqualsFilter('status', 'pending'))->addAssociation('execution');
        $tasks = $repository->search($criteria, $context);
        $actualAssignees = [];
        foreach ($tasks as $task) {
            static::assertInstanceOf(ApprovalTaskEntity::class, $task);
            static::assertSame($executionId, $task->getExecution()?->getId());
            $actualAssignees[] = $task->getAssigneeId();
        }
        static::assertEqualsCanonicalizing($assignees, $actualAssignees);
        static::assertEquals($receipt, $this->service->enterApproval($this->instanceId, $executionId, $commandId, 2, $context));
        static::assertSame(2, $this->countTasks($executionId));
        static::assertSame(2, $this->receiptCount());
        $this->expectExceptionObject(ApprovalFlowException::invalidApprovalEntry($executionId, 'The current status is awaiting_approval.'));

        $this->service->enterApproval($this->instanceId, $executionId, Uuid::randomHex(), 3, $context);
    }

    public function testInactiveAssigneeCannotSilentlyReduceTheApprovalGroup(): void
    {
        $inactive = $this->createUser(active: false);
        $executionId = $this->queueApproval([$this->createUser(active: true), $inactive]);
        $this->expectExceptionObject(ApprovalFlowException::assigneeUnavailable($inactive));

        try {
            $this->service->enterApproval($this->instanceId, $executionId, Uuid::randomHex(), 2, Context::createDefaultContext());
        } finally {
            static::assertSame(0, $this->countTasks($executionId));
            static::assertSame(['completed', 'waiting'], array_column($this->executions(), 'status'));
            static::assertSame(1, $this->receiptCount());
        }
    }

    public function testAllApproversMustAgreeBeforeTheExecutionCompletes(): void
    {
        $first = $this->createUser(active: true);
        $second = $this->createUser(active: true);
        $executionId = $this->openApproval([$first, $second]);
        $commandId = Uuid::randomHex();
        $context = $this->reviewerContext($first);
        $taskId = $this->taskIdFor($first);
        // Decisions consume the frozen group policy, even when deployment artifacts are unavailable.
        $this->filesystem->remove($this->directory);

        $receipt = $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, 'Checked.', $context);

        static::assertSame(4, $receipt->aggregateVersion);
        static::assertSame(Uuid::fromHexToBytes($commandId), $this->connection->fetchOne('SELECT completion_command_id FROM approval_flow_task WHERE id = :id', ['id' => Uuid::fromHexToBytes($taskId)]));
        static::assertSame(['approved', 'pending'], $this->taskStatuses([$first, $second]));
        static::assertSame('awaiting_approval', $this->executions()[1]['status']);
        static::assertNull($this->executions()[1]['outcome']);
        $this->service->decide($this->instanceId, $this->taskIdFor($second), Uuid::randomHex(), 4, ApprovalOutcome::Approved, 'Agreed.', $this->reviewerContext($second));
        static::assertSame(['approved', 'approved'], $this->taskStatuses([$first, $second]));
        static::assertSame('completed', $this->executions()[1]['status']);
        static::assertSame('approved', $this->executions()[1]['outcome']);
        static::assertEquals($receipt, $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, 'Checked.', $context));
        static::assertSame(4, $this->receiptCount());
        $operation = $this->connection->fetchAssociative('SELECT actor_json, reason FROM approval_flow_operation WHERE instance_id = :instance AND command_id = :command', [
            'instance' => Uuid::fromHexToBytes($this->instanceId), 'command' => Uuid::fromHexToBytes($commandId),
        ]);
        static::assertNotFalse($operation);
        static::assertSame('Checked.', $operation['reason']);
        static::assertSame(['source' => 'admin-api', 'userId' => $first, 'integrationId' => null], json_decode($operation['actor_json'], true, flags: \JSON_THROW_ON_ERROR));
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_execution');
        $execution = $repository->search(new Criteria([$executionId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(ApprovalNodeExecutionEntity::class, $execution);
        static::assertSame(ApprovalOutcome::Approved, $execution->getOutcome());
    }

    public function testAnyApprovalCompletesTheExecutionAndCancelsOtherTasks(): void
    {
        $first = $this->createUser(active: true);
        $second = $this->createUser(active: true);
        $this->openApproval([$first, $second], policy: 'core:any');

        $this->service->decide($this->instanceId, $this->taskIdFor($first), Uuid::randomHex(), 3, ApprovalOutcome::Approved, '', $this->reviewerContext($first));

        static::assertSame(['approved', 'cancelled'], $this->taskStatuses([$first, $second]));
        static::assertSame('approved', $this->executions()[1]['outcome']);
        $taskId = $this->taskIdFor($second);
        $this->expectExceptionObject(ApprovalFlowException::taskNotPending($taskId));

        $this->service->decide($this->instanceId, $taskId, Uuid::randomHex(), 4, ApprovalOutcome::Rejected, 'Late objection.', $this->reviewerContext($second));
    }

    #[DataProvider('approvalPolicies')]
    public function testRejectionVetoesTheGroupAndCancelsRemainingTasks(string $policy): void
    {
        $first = $this->createUser(active: true);
        $second = $this->createUser(active: true);
        $this->openApproval([$first, $second], $policy);

        $this->service->decide($this->instanceId, $this->taskIdFor($first), Uuid::randomHex(), 3, ApprovalOutcome::Rejected, 'Rejected.', $this->reviewerContext($first));

        static::assertSame(['rejected', 'cancelled'], $this->taskStatuses([$first, $second]));
        static::assertSame('completed', $this->executions()[1]['status']);
        static::assertSame('rejected', $this->executions()[1]['outcome']);
    }

    public static function approvalPolicies(): iterable
    {
        yield 'all' => ['core:all'];
        yield 'any with explicit veto' => ['core:any'];
    }

    public function testSystemSourceCannotCastAPersonalApprovalDecision(): void
    {
        $this->expectException(MissingPrivilegeException::class);

        $this->service->decide('invalid', 'invalid', 'invalid', 0, ApprovalOutcome::Approved, '', Context::createDefaultContext());
    }

    public function testDecisionPrivilegeIsCheckedBeforeInputAndTaskLookup(): void
    {
        $this->expectException(MissingPrivilegeException::class);

        $this->service->decide('invalid', 'invalid', 'invalid', 0, ApprovalOutcome::Approved, '', Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testAnIntegrationCannotSupplyAPersonalDecision(): void
    {
        $source = new AdminApiSource(null, Uuid::randomHex());
        $source->setPermissions([ApprovalInstanceService::DECIDE_PRIVILEGE]);
        $this->expectException(MissingPrivilegeException::class);

        $this->service->decide('invalid', 'invalid', 'invalid', 0, ApprovalOutcome::Approved, '', Context::createDefaultContext($source));
    }

    public function testCommandIdentityCannotBeReusedForAnotherDecision(): void
    {
        $assignee = $this->createUser(active: true);
        $this->openApproval([$assignee]);
        $taskId = $this->taskIdFor($assignee);
        $commandId = Uuid::randomHex();
        $context = $this->reviewerContext($assignee);
        $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, 'Agreed.', $context);
        $this->expectExceptionObject(ApprovalFlowException::instanceCommandConflict($this->instanceId, $commandId));

        $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Rejected, 'Agreed.', $context);
    }

    public function testSuspensionBlocksNewVotesButPreservesAnAuthorizedReceipt(): void
    {
        $first = $this->createUser(active: true);
        $second = $this->createUser(active: true);
        $this->openApproval([$first, $second]);
        $taskId = $this->taskIdFor($first);
        $commandId = Uuid::randomHex();
        $context = $this->reviewerContext($first);
        $receipt = $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, '', $context);
        $this->service->suspend($this->instanceId, Uuid::randomHex(), 4, 'Review required.', Context::createDefaultContext());

        static::assertEquals($receipt, $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, '', $context));
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'suspended', 'decide'));

        $this->service->decide($this->instanceId, $this->taskIdFor($second), Uuid::randomHex(), 5, ApprovalOutcome::Approved, '', $this->reviewerContext($second));
    }

    public function testOrdinaryDalWritesCannotCompleteTasks(): void
    {
        $assignee = $this->createUser(active: true);
        $this->openApproval([$assignee]);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_task');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->update([['id' => $this->taskIdFor($assignee), 'status' => 'approved']], Context::createDefaultContext());
    }

    public function testEvenAnAdministratorCannotVoteForAnotherAssignee(): void
    {
        $assignee = $this->createUser(active: true);
        $other = $this->createUser(active: true);
        $this->openApproval([$assignee]);
        $taskId = $this->taskIdFor($assignee);
        $source = new AdminApiSource($other);
        $source->setIsAdmin(true);
        $this->expectExceptionObject(ApprovalFlowException::taskAssigneeRequired());

        $this->service->decide($this->instanceId, $taskId, Uuid::randomHex(), 3, ApprovalOutcome::Approved, '', Context::createDefaultContext($source));
    }

    public function testDecisionRetryStillRequiresAnActiveAssignee(): void
    {
        $assignee = $this->createUser(active: true);
        $this->openApproval([$assignee], policy: 'core:single');
        $taskId = $this->taskIdFor($assignee);
        $commandId = Uuid::randomHex();
        $context = $this->reviewerContext($assignee);
        $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, '', $context);
        $this->connection->update('user', ['active' => 0], ['id' => Uuid::fromHexToBytes($assignee)]);
        $this->expectExceptionObject(ApprovalFlowException::taskAssigneeRequired());

        $this->service->decide($this->instanceId, $taskId, $commandId, 3, ApprovalOutcome::Approved, '', $context);
    }

    public function testReceiptFailureRollsBackDecisionOutcomeAndCancellation(): void
    {
        $first = $this->createUser(active: true);
        $second = $this->createUser(active: true);
        $this->openApproval([$first, $second], policy: 'core:any');
        $failure = new \RuntimeException('Decision receipt failed.');
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_operation.written', static function () use ($failure): void {
            throw $failure;
        });
        $this->expectExceptionObject($failure);

        try {
            $this->service->decide($this->instanceId, $this->taskIdFor($first), Uuid::randomHex(), 3, ApprovalOutcome::Approved, '', $this->reviewerContext($first));
        } finally {
            static::assertSame(['pending', 'pending'], $this->taskStatuses([$first, $second]));
            static::assertNull($this->executions()[1]['outcome']);
            static::assertSame('awaiting_approval', $this->executions()[1]['status']);
            static::assertSame(2, $this->receiptCount());
            static::assertSame(3, (int) $this->connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public function testExecutionFromAnotherInstanceCannotCreateTasks(): void
    {
        $executionId = $this->queueApproval([$this->createUser(active: true)]);
        $other = InstanceFixture::create($this->connection, $this->releaseId, ApprovalInstanceStatus::Running);
        $this->expectExceptionObject(ApprovalFlowException::executionNotFound($executionId, $other));

        $this->service->enterApproval($other, $executionId, Uuid::randomHex(), 1, Context::createDefaultContext());
    }

    public function testAnOlderSnapshotCannotAssignADeactivatedUser(): void
    {
        $writer = DriverManager::getConnection($this->connection->getParams());
        $isolation = $this->connection->getTransactionIsolation();
        $this->connection->rollBack();
        $assignee = null;
        $avatarId = null;

        try {
            $assignee = TestUser::createNewTestUser($writer)->getUserId();
            $userKey = ['id' => Uuid::fromHexToBytes($assignee)];
            $avatarId = $writer->fetchOne('SELECT avatar_id FROM `user` WHERE id = :id', $userKey);
            $this->connection->setTransactionIsolation(TransactionIsolationLevel::REPEATABLE_READ);
            $this->connection->beginTransaction();
            $executionId = $this->queueApproval([$assignee]);
            static::assertSame(1, (int) $this->connection->fetchOne('SELECT active FROM `user` WHERE id = :id', $userKey));
            $writer->update('user', ['active' => 0], $userKey);

            $this->expectExceptionObject(ApprovalFlowException::assigneeUnavailable($assignee));
            $this->service->enterApproval($this->instanceId, $executionId, Uuid::randomHex(), 2, Context::createDefaultContext());
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $this->connection->setTransactionIsolation($isolation);
            $this->connection->beginTransaction();
            if ($assignee !== null) {
                $writer->delete('user', ['id' => Uuid::fromHexToBytes($assignee)]);
            }
            if (\is_string($avatarId)) {
                $writer->delete('media', ['id' => $avatarId]);
            }
            $writer->close();
        }
    }

    public function testTaskRecordsRequireAnInstanceCommand(): void
    {
        $assignee = $this->createUser(active: true);
        $executionId = $this->queueApproval([$assignee]);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_task');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->create([[
            'id' => Uuid::randomHex(),
            'executionId' => $executionId,
            'assigneeId' => $assignee,
            'status' => 'pending',
        ]], Context::createDefaultContext());
    }

    public function testReceiptFailureRollsBackTasksAndApprovalEntry(): void
    {
        $executionId = $this->queueApproval([$this->createUser(active: true)]);
        $failure = new \RuntimeException('Approval command receipt failed.');
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_operation.written', static function () use ($failure): void {
            throw $failure;
        });
        $this->expectExceptionObject($failure);

        try {
            $this->service->enterApproval($this->instanceId, $executionId, Uuid::randomHex(), 2, Context::createDefaultContext());
        } finally {
            static::assertSame(0, $this->countTasks($executionId));
            static::assertSame(['completed', 'waiting'], array_column($this->executions(), 'status'));
            static::assertSame(1, $this->receiptCount());
            static::assertSame(2, (int) $this->connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
        }
    }

    public function testAdministrativeManagementPrivilegeDoesNotAuthorizeNodeEntry(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setPermissions([ApprovalInstanceService::MANAGE_PRIVILEGE]);
        $this->expectException(MissingPrivilegeException::class);

        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 0, Context::createDefaultContext($source));
    }

    public function testSuspendedInstanceCannotEnterStart(): void
    {
        $context = Context::createDefaultContext();
        $this->service->suspend($this->instanceId, Uuid::randomHex(), 1, 'Review required.', $context);
        $this->expectExceptionObject(ApprovalFlowException::invalidInstanceTransition($this->instanceId, 'suspended', 'enter-start'));

        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 2, $context);
    }

    public function testInvalidReleaseLeavesTheInstanceAndTraceUnchanged(): void
    {
        $this->connection->update('approval_flow_release', ['contract_json' => '{}'], ['id' => Uuid::fromHexToBytes($this->releaseId)]);
        $this->expectExceptionObject(ApprovalFlowException::releaseVerificationFailed($this->releaseId, 'contractJson'));

        try {
            $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());
        } finally {
            $this->assertNoEntryWasCommitted();
        }
    }

    public function testAStartWithMultipleOutgoingEdgesCannotBeEnteredYet(): void
    {
        $graph = json_decode(GraphFixture::json(), flags: \JSON_THROW_ON_ERROR);
        static::assertInstanceOf(\stdClass::class, $graph);
        static::assertIsArray($graph->nodes);
        static::assertIsArray($graph->connections);
        $start = $graph->nodes[0];
        $end = $graph->nodes[2];
        static::assertInstanceOf(\stdClass::class, $start);
        static::assertInstanceOf(\stdClass::class, $end);
        static::assertIsArray($start->ports);
        static::assertIsArray($end->ports);
        static::assertInstanceOf(\stdClass::class, $start->ports[0]);
        static::assertInstanceOf(\stdClass::class, $end->ports[0]);

        $start->ports[0]->cardinality = 'many';
        $end->ports[0]->cardinality = 'many';
        $graph->connections[] = [
            'id' => 'start-end',
            'source' => 'start',
            'target' => 'end',
            'sourcePort' => 'out',
            'targetPort' => 'in',
            'condition' => ['kind' => 'always'],
            'extensions' => new \stdClass(),
        ];
        $releaseId = $this->publish(json_encode($graph, \JSON_THROW_ON_ERROR));
        $this->instanceId = InstanceFixture::create($this->connection, $releaseId, ApprovalInstanceStatus::Running);
        $this->expectExceptionObject(ApprovalFlowException::unsupportedStartRoute($releaseId));

        try {
            $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());
        } finally {
            $this->assertNoEntryWasCommitted();
        }
    }

    public function testExecutionRecordsRequireAnInstanceCommand(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_execution');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->create([[
            'id' => Uuid::randomHex(),
            'instanceId' => $this->instanceId,
            'nodeId' => 'start',
            'sequence' => 1,
            'status' => ApprovalNodeExecutionStatus::Completed->value,
        ]], Context::createDefaultContext());
    }

    public function testReceiptWriteFailureRollsBackBothExecutionsAndTheVersion(): void
    {
        $failure = new \RuntimeException('Receipt persistence failed.');
        // Fail after the actual DAL receipt write to exercise the outer rollback.
        EventHookDispatcher::fromContainer(static::getContainer())->on('approval_flow_operation.written', static function () use ($failure): void {
            throw $failure;
        });
        $this->expectExceptionObject($failure);

        try {
            $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());
        } finally {
            $this->assertNoEntryWasCommitted();
        }
    }

    private function publish(string $graph): string
    {
        return ApprovalRuntimeFixture::publish(static::getContainer(), $this->runtime, $graph);
    }

    private function createUser(bool $active): string
    {
        $id = Uuid::randomHex();
        $localeId = $this->connection->fetchOne('SELECT id FROM locale LIMIT 1');
        static::assertIsString($localeId);
        static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('user')->create([[
            'id' => $id,
            'username' => $id,
            'name' => 'Approval reviewer',
            'email' => $id . '@example.test',
            'password' => 'shopwell',
            'active' => $active,
            'localeId' => Uuid::fromBytesToHex($localeId),
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @param non-empty-list<string> $assignees
     */
    private function queueApproval(array $assignees, string $policy = 'core:all'): string
    {
        $graph = str_replace('"finance-user"', implode(', ', array_map(static fn (string $id): string => json_encode($id, \JSON_THROW_ON_ERROR), $assignees)), GraphFixture::json());
        $graph = str_replace('"core:all"', json_encode($policy, \JSON_THROW_ON_ERROR), $graph);
        $releaseId = $this->publish($graph);
        $this->instanceId = InstanceFixture::create($this->connection, $releaseId, ApprovalInstanceStatus::Running);
        $this->service->enterStart($this->instanceId, Uuid::randomHex(), 1, Context::createDefaultContext());

        return Uuid::fromBytesToHex($this->executions()[1]['id']);
    }

    /**
     * @param non-empty-list<string> $assignees
     */
    private function openApproval(array $assignees, string $policy = 'core:all'): string
    {
        $executionId = $this->queueApproval($assignees, $policy);
        $this->service->enterApproval($this->instanceId, $executionId, Uuid::randomHex(), 2, Context::createDefaultContext());

        return $executionId;
    }

    private function reviewerContext(string $userId): Context
    {
        $source = new AdminApiSource($userId);
        $source->setPermissions(['approval_flow_task:decide']);

        return Context::createDefaultContext($source);
    }

    private function taskIdFor(string $assignee): string
    {
        $id = $this->connection->fetchOne('SELECT task.id FROM approval_flow_task task INNER JOIN approval_flow_execution execution ON execution.id = task.execution_id WHERE execution.instance_id = :instance AND task.assignee_id = :assignee', [
            'instance' => Uuid::fromHexToBytes($this->instanceId), 'assignee' => Uuid::fromHexToBytes($assignee),
        ]);
        static::assertIsString($id);

        return Uuid::fromBytesToHex($id);
    }

    /**
     * @param list<string> $assignees
     *
     * @return list<string>
     */
    private function taskStatuses(array $assignees): array
    {
        return array_map(function (string $assignee): string {
            $status = $this->connection->fetchOne('SELECT status FROM approval_flow_task WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->taskIdFor($assignee))]);
            static::assertIsString($status);

            return $status;
        }, $assignees);
    }

    private function countTasks(string $executionId): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM approval_flow_task WHERE execution_id = :execution', ['execution' => Uuid::fromHexToBytes($executionId)]);
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

    private function assertNoEntryWasCommitted(): void
    {
        static::assertSame([], $this->executions());
        static::assertSame(0, $this->receiptCount());
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT aggregate_version FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->instanceId)]));
    }
}
