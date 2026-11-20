<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1795137733CreateApprovalFlowResultOutbox;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795137733CreateApprovalFlowResultOutbox::class)]
class Migration1795137733CreateApprovalFlowResultOutboxTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    private string $instanceId;

    private string $releaseId;

    private string $commandId;

    private string $resultId;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'approval_result_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        $this->connection->executeStatement('CREATE TABLE approval_flow_instance (id BINARY(16) NOT NULL PRIMARY KEY) ENGINE = InnoDB');
        $this->connection->executeStatement('CREATE TABLE approval_flow_release (id BINARY(16) NOT NULL PRIMARY KEY) ENGINE = InnoDB');
        $this->connection->executeStatement('CREATE TABLE approval_flow_operation (instance_id BINARY(16) NOT NULL, command_id BINARY(16) NOT NULL, PRIMARY KEY (instance_id, command_id)) ENGINE = InnoDB');
        (new Migration1795137733CreateApprovalFlowResultOutbox())->update($this->connection);
        $this->instanceId = Uuid::randomBytes();
        $this->releaseId = Uuid::randomBytes();
        $this->commandId = Uuid::randomBytes();
        $this->resultId = Uuid::randomBytes();
        $this->connection->insert('approval_flow_instance', ['id' => $this->instanceId]);
        $this->connection->insert('approval_flow_release', ['id' => $this->releaseId]);
        $this->connection->insert('approval_flow_operation', ['instance_id' => $this->instanceId, 'command_id' => $this->commandId]);
        $this->connection->insert('approval_flow_result', $this->resultRow($this->resultId));
        $this->connection->insert('approval_flow_outbox', [
            'result_id' => $this->resultId, 'status' => 'pending', 'attempt' => 0,
            'next_attempt_at' => '2026-11-20 00:00:00.123', 'created_at' => '2026-11-20 00:00:00.123',
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testRepeatedMigrationPreservesTheResultDeliveryAndIndexes(): void
    {
        (new Migration1795137733CreateApprovalFlowResultOutbox())->update($this->connection);

        static::assertSame($this->resultId, $this->connection->fetchOne('SELECT id FROM approval_flow_result'));
        static::assertSame('rejected', $this->connection->fetchOne('SELECT outcome FROM approval_flow_result'));
        static::assertSame(['status' => 'pending', 'lease_until' => null], $this->connection->fetchAssociative('SELECT status, lease_until FROM approval_flow_outbox'));
        $schema = $this->connection->createSchemaManager();
        $result = $schema->introspectTableByUnquotedName('approval_flow_result');
        $outbox = $schema->introspectTableByUnquotedName('approval_flow_outbox');
        static::assertSame(IndexType::UNIQUE, $result->getIndex('uniq.approval_flow_result.instance')->getType());
        static::assertTrue($outbox->hasIndex('idx.approval_flow_outbox.due'));
        static::assertTrue($outbox->hasIndex('idx.approval_flow_outbox.lease'));
        $referencedTable = $outbox->getForeignKey('fk.approval_flow_outbox.result')->getReferencedTableName();
        static::assertSame('approval_flow_result', $referencedTable->getUnqualifiedName()->getValue());
    }

    public function testAnotherCommandCannotCreateASecondTerminalResult(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert('approval_flow_result', $this->resultRow(Uuid::randomBytes()));
    }

    public function testTheTerminalReceiptCannotBeDeletedWhileAResultReferencesIt(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->connection->delete('approval_flow_operation', ['instance_id' => $this->instanceId, 'command_id' => $this->commandId]);
    }

    /**
     * @return array<string, int|string>
     */
    private function resultRow(string $id): array
    {
        return [
            'id' => $id, 'instance_id' => $this->instanceId, 'release_id' => $this->releaseId, 'command_id' => $this->commandId,
            'aggregate_version' => 2, 'outcome' => 'rejected', 'finished_at' => '2026-11-20 00:00:00.123', 'created_at' => '2026-11-20 00:00:00.123',
        ];
    }
}
