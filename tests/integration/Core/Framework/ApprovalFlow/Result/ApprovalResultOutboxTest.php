<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Result;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStatus;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceWriteGuard;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultEntity;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultOutbox;
use Shopwell\Core\Framework\ApprovalFlow\Result\ApprovalResultStore;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\_fixtures\InstanceFixture;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalResultOutboxTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private MockClock $clock;

    private ApprovalResultOutbox $outbox;

    private ApprovalResultStore $results;

    private string $releaseId;

    private string $definitionId;

    /**
     * @var list<string>
     */
    private array $instances;

    protected function setUp(): void
    {
        $kernel = static::getContainer();
        $this->connection = $kernel->get(Connection::class);
        $this->clock = new MockClock('2026-11-20 12:00:00.123 UTC');
        $this->outbox = new ApprovalResultOutbox($this->connection, $this->clock);
        $this->results = new ApprovalResultStore(
            $this->connection,
            $kernel->get('approval_flow_result.repository'),
            $kernel->get(ApprovalInstanceWriteGuard::class),
            $this->clock,
        );
        $this->releaseId = InstanceFixture::publish($kernel);
        $definition = $this->connection->fetchOne('SELECT definition_id FROM approval_flow_release WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->releaseId)]);
        static::assertIsString($definition);
        $this->definitionId = $definition;
        $this->instances = [];
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        foreach ($this->instances as $id) {
            $key = ['id' => Uuid::fromHexToBytes($id)];
            $this->connection->executeStatement('DELETE delivery FROM approval_flow_outbox delivery INNER JOIN approval_flow_result result ON result.id = delivery.result_id WHERE result.instance_id = :id', $key);
            $this->connection->delete('approval_flow_result', ['instance_id' => $key['id']]);
            $this->connection->delete('approval_flow_operation', ['instance_id' => $key['id']]);
            $this->connection->delete('approval_flow_instance', $key);
        }
        $this->connection->delete('approval_flow_release', ['id' => Uuid::fromHexToBytes($this->releaseId)]);
        $this->connection->delete('approval_flow_draft', ['definition_id' => $this->definitionId]);
        $this->connection->delete('approval_flow_definition', ['id' => $this->definitionId]);
    }

    public function testClaimReturnsTheCommittedImmutableResultAndAcknowledgementOnlyChangesDelivery(): void
    {
        $instanceId = $this->recordResult();
        $context = Context::createDefaultContext();

        $delivery = $this->outbox->claim($context);

        static::assertNotNull($delivery);
        static::assertFalse($this->connection->isTransactionActive());
        static::assertSame($instanceId, $delivery->result->instanceId);
        static::assertSame($this->releaseId, $delivery->result->releaseId);
        static::assertSame(2, $delivery->result->aggregateVersion);
        static::assertSame(ApprovalOutcome::Approved, $delivery->result->outcome);
        static::assertEquals($this->clock->now(), $delivery->result->finishedAt);
        static::assertSame(1, $delivery->attempt);
        static::assertNull($this->outbox->claim($context));
        static::assertTrue($this->outbox->acknowledge($delivery->result->id, $delivery->attempt, $context));
        static::assertFalse($this->outbox->acknowledge($delivery->result->id, $delivery->attempt, $context));
        static::assertNull($this->outbox->claim($context));
        static::assertSame('delivered', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox WHERE result_id = :id', ['id' => Uuid::fromHexToBytes($delivery->result->id)]));
        static::assertSame(['status' => 'completed', 'outcome' => 'approved'], $this->connection->fetchAssociative('SELECT status, outcome FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_result');
        $result = $repository->search(new Criteria([$delivery->result->id]), $context)->first();
        static::assertInstanceOf(ApprovalResultEntity::class, $result);
        static::assertSame(ApprovalOutcome::Approved, $result->getOutcome());
        static::assertEquals($delivery->result->finishedAt, $result->getFinishedAt());
    }

    public function testExpiredWorkerCannotAcknowledgeOrRescheduleTheNewAttempt(): void
    {
        $this->recordResult();
        $context = Context::createDefaultContext();
        $first = $this->outbox->claim($context);
        static::assertNotNull($first);
        $this->clock->sleep(ApprovalResultOutbox::LEASE_SECONDS);
        static::assertFalse($this->outbox->acknowledge($first->result->id, $first->attempt, $context));
        $second = $this->outbox->claim($context);
        static::assertNotNull($second);
        static::assertSame($first->result->id, $second->result->id);
        static::assertSame(2, $second->attempt);
        static::assertFalse($this->outbox->acknowledge($first->result->id, $first->attempt, $context));
        static::assertFalse($this->outbox->retry($first->result->id, $first->attempt, $context));
        static::assertTrue($this->outbox->acknowledge($second->result->id, $second->attempt, $context));
    }

    public function testFailureRetriesAfterBackoffAndEventuallyKeepsAFailedDelivery(): void
    {
        $instanceId = $this->recordResult();
        $context = Context::createDefaultContext();
        $resultId = null;
        for ($attempt = 1; $attempt <= ApprovalResultOutbox::MAX_ATTEMPTS; ++$attempt) {
            $delivery = $this->outbox->claim($context);
            static::assertNotNull($delivery);
            $resultId ??= $delivery->result->id;
            static::assertSame($resultId, $delivery->result->id);
            static::assertSame($attempt, $delivery->attempt);
            static::assertTrue($this->outbox->retry($resultId, $attempt, $context));
            static::assertNull($this->outbox->claim($context));
            $this->clock->sleep(86400);
        }
        static::assertIsString($resultId);
        static::assertSame('failed', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox WHERE result_id = :id', ['id' => Uuid::fromHexToBytes($resultId)]));
        static::assertSame('completed', $this->connection->fetchOne('SELECT status FROM approval_flow_instance WHERE id = :id', ['id' => Uuid::fromHexToBytes($instanceId)]));
        static::assertFalse($this->outbox->acknowledge($resultId, ApprovalResultOutbox::MAX_ATTEMPTS, $context));
    }

    public function testRetryDoesNotBecomeDueBeforeItsBackoffExpires(): void
    {
        $this->recordResult();
        $context = Context::createDefaultContext();
        $first = $this->outbox->claim($context);
        static::assertNotNull($first);
        static::assertTrue($this->outbox->retry($first->result->id, $first->attempt, $context));
        $this->clock->sleep(4);
        static::assertNull($this->outbox->claim($context));
        $this->clock->sleep(1);

        $second = $this->outbox->claim($context);

        static::assertNotNull($second);
        static::assertSame($first->result->id, $second->result->id);
        static::assertSame(2, $second->attempt);
    }

    public function testExpiredFinalAttemptIsFailedAndDoesNotHideTheNextResult(): void
    {
        $first = $this->recordResult();
        $second = $this->recordResult();
        $context = Context::createDefaultContext();
        $delivery = $this->outbox->claim($context);
        static::assertNotNull($delivery);
        $this->connection->update('approval_flow_outbox', ['attempt' => ApprovalResultOutbox::MAX_ATTEMPTS], ['result_id' => Uuid::fromHexToBytes($delivery->result->id)]);
        $this->clock->sleep(ApprovalResultOutbox::LEASE_SECONDS);

        $next = $this->outbox->claim($context);

        static::assertNotNull($next);
        static::assertNotSame($delivery->result->id, $next->result->id);
        static::assertContains($next->result->instanceId, [$first, $second]);
        static::assertSame('failed', $this->connection->fetchOne('SELECT status FROM approval_flow_outbox WHERE result_id = :id', ['id' => Uuid::fromHexToBytes($delivery->result->id)]));
    }

    public function testLockedDeliveryIsSkippedByAnotherConnection(): void
    {
        $this->recordResult();
        $this->recordResult();
        $writer = DriverManager::getConnection($this->connection->getParams());
        $writer->beginTransaction();
        try {
            $locked = $writer->fetchOne('SELECT result_id FROM approval_flow_outbox ORDER BY next_attempt_at, result_id LIMIT 1');
            static::assertIsString($locked);
            $writer->fetchOne('SELECT result_id FROM approval_flow_outbox WHERE result_id = :id FOR UPDATE', ['id' => $locked]);

            $delivery = $this->outbox->claim(Context::createDefaultContext());

            static::assertNotNull($delivery);
            static::assertNotSame(Uuid::fromBytesToHex($locked), $delivery->result->id);
        } finally {
            $writer->rollBack();
            $writer->close();
        }
    }

    public function testUncommittedResultCannotBeClaimedByAnotherConnection(): void
    {
        $writer = DriverManager::getConnection($this->connection->getParams());
        $other = new ApprovalResultOutbox($writer, $this->clock);
        $this->connection->beginTransaction();
        try {
            $this->recordResult();

            static::assertNull($other->claim(Context::createDefaultContext()));
            $this->connection->commit();
            static::assertNotNull($other->claim(Context::createDefaultContext()));
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $writer->close();
        }
    }

    public function testClaimRejectsTheCallersUncommittedTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->expectExceptionObject(ApprovalFlowException::resultDeliveryTransactionActive());
        try {
            $this->outbox->claim(Context::createDefaultContext());
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testAdministrationCannotClaimAResultEvenWithFullPrivileges(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(true);
        $this->expectException(MissingPrivilegeException::class);

        $this->outbox->claim(Context::createDefaultContext($source));
    }

    public function testAdministrationCannotAcknowledgeAResult(): void
    {
        $this->expectException(MissingPrivilegeException::class);

        $this->outbox->acknowledge('invalid', 0, Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testAdministrationCannotRescheduleAResult(): void
    {
        $this->expectException(MissingPrivilegeException::class);

        $this->outbox->retry('invalid', 0, Context::createDefaultContext(new AdminApiSource(Uuid::randomHex())));
    }

    public function testConfirmationRejectsAnInvalidDeliveryIdentity(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::invalidResultDelivery());

        $this->outbox->acknowledge('invalid', 0, Context::createDefaultContext());
    }

    public function testAResultCannotBeRecordedWithoutItsCommandTransaction(): void
    {
        $this->expectExceptionObject(ApprovalFlowException::resultTransactionRequired());

        $this->results->record(Uuid::randomHex(), $this->releaseId, Uuid::randomHex(), 2, ApprovalOutcome::Approved, Context::createDefaultContext());
    }

    public function testOrdinaryDalCannotCreateAResult(): void
    {
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_result');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->create([[
            'id' => Uuid::randomHex(), 'instanceId' => Uuid::randomHex(), 'releaseId' => $this->releaseId,
            'commandId' => Uuid::randomHex(), 'aggregateVersion' => 2, 'outcome' => 'approved', 'finishedAt' => $this->clock->now(),
        ]], Context::createDefaultContext());
    }

    public function testOrdinaryDalCannotDeleteAResult(): void
    {
        $this->recordResult();
        $delivery = $this->outbox->claim(Context::createDefaultContext());
        static::assertNotNull($delivery);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_result');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->delete([['id' => $delivery->result->id]], Context::createDefaultContext());
    }

    public function testOrdinaryDalCannotOverwriteAResult(): void
    {
        $this->recordResult();
        $delivery = $this->outbox->claim(Context::createDefaultContext());
        static::assertNotNull($delivery);
        $repository = static::getContainer()->get(DefinitionInstanceRegistry::class)->getRepository('approval_flow_result');
        $this->expectExceptionObject(ApprovalFlowException::uncontrolledInstanceWrite());

        $repository->update([['id' => $delivery->result->id, 'outcome' => 'rejected']], Context::createDefaultContext());
    }

    private function recordResult(): string
    {
        $id = InstanceFixture::create($this->connection, $this->releaseId, ApprovalInstanceStatus::Completed);
        $this->instances[] = $id;
        $commandId = Uuid::randomHex();
        $this->connection->update('approval_flow_instance', ['outcome' => 'approved', 'aggregate_version' => 2], ['id' => Uuid::fromHexToBytes($id)]);
        $this->connection->insert('approval_flow_operation', [
            'instance_id' => Uuid::fromHexToBytes($id), 'command_id' => Uuid::fromHexToBytes($commandId),
            'operation' => 'enter-end', 'request_hash' => str_repeat('a', 64), 'actor_json' => '{"source":"system"}', 'reason' => 'Published end.',
            'previous_status' => 'running', 'status' => 'completed', 'outcome' => 'approved', 'previous_version' => 1, 'aggregate_version' => 2,
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
        $this->connection->transactional(function () use ($id, $commandId): void {
            $this->results->record($id, $this->releaseId, $commandId, 2, ApprovalOutcome::Approved, Context::createDefaultContext());
        });

        return $id;
    }
}
