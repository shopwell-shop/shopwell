<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Draft\ApprovalDraftService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionEntity;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Node\ApprovalNodeExecutionStatus;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\_fixtures\GraphFixture;
use Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures\PublicationFixture;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalStartNodeTest extends TestCase
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
        $this->directory = sys_get_temp_dir() . '/approval-start-' . Uuid::randomHex();
        $manifest = PublicationFixture::writeManifest($this->filesystem, $this->directory);
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $runtime = $this->runtime = PublicationFixture::container($manifest);
        $services = [
            Connection::class => $this->connection,
            ApprovalReleaseStore::class => $kernel->get(ApprovalReleaseStore::class),
            ApprovalInstanceWriteGuard::class => $kernel->get(ApprovalInstanceWriteGuard::class),
        ];
        $definitions = $kernel->get(DefinitionInstanceRegistry::class);
        foreach (['approval_flow_instance', 'approval_flow_operation', 'approval_flow_execution'] as $entityName) {
            $services[$entityName . '.repository'] = $definitions->getRepository($entityName);
        }
        foreach ($services as $id => $service) {
            $runtime->register($id)->setSynthetic(true)->setPublic(true);
        }
        $runtime->getDefinition(ApprovalInstanceService::class)->setPublic(true);
        $runtime->getDefinition(ApprovalReleaseService::class)->setPublic(true);
        $runtime->compile();
        foreach ($services as $id => $service) {
            $runtime->set($id, $service);
        }

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
        $context = Context::createDefaultContext();
        $draft = static::getContainer()->get(ApprovalDraftService::class)->create('Start node fixture', $graph, $context);
        $compiler = $this->runtime->get(ApprovalReleaseCompiler::class);
        $publisher = $this->runtime->get(ApprovalReleaseService::class);
        static::assertInstanceOf(ApprovalReleaseCompiler::class, $compiler);
        static::assertInstanceOf(ApprovalReleaseService::class, $publisher);
        $compiled = $compiler->compile($draft->graph);

        return $publisher->publish($draft->definitionId, $draft->revision, $compiled->hash, $context)->id;
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
