<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1795084152CreateApprovalFlowInstanceTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795084152CreateApprovalFlowInstanceTables::class)]
class Migration1795084152CreateApprovalFlowInstanceTablesTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'approval_instance_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        $this->connection->executeStatement('CREATE TABLE approval_flow_release (id BINARY(16) NOT NULL PRIMARY KEY) ENGINE = InnoDB');
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testFreshInstallationAndRepeatedMigrationPreserveInstanceAndReceiptConstraints(): void
    {
        $migration = new Migration1795084152CreateApprovalFlowInstanceTables();
        $migration->update($this->connection);
        $releaseId = Uuid::randomBytes();
        $instanceId = Uuid::randomBytes();
        $commandId = Uuid::randomBytes();
        $this->connection->insert('approval_flow_release', ['id' => $releaseId]);
        $this->connection->insert('approval_flow_instance', [
            'id' => $instanceId, 'release_id' => $releaseId, 'status' => 'suspended', 'aggregate_version' => 2, 'created_at' => '2026-11-19 00:00:00.000',
        ]);
        $this->connection->insert('approval_flow_operation', [
            'instance_id' => $instanceId, 'command_id' => $commandId, 'operation' => 'suspend', 'request_hash' => str_repeat('a', 64),
            'actor_json' => '{"source":"system"}', 'reason' => 'Review required.', 'previous_status' => 'running', 'status' => 'suspended',
            'previous_version' => 1, 'aggregate_version' => 2, 'created_at' => '2026-11-19 00:00:00.000',
        ]);

        $migration->update($this->connection);

        static::assertSame($instanceId, $this->connection->fetchOne('SELECT id FROM approval_flow_instance'));
        static::assertSame($commandId, $this->connection->fetchOne('SELECT command_id FROM approval_flow_operation'));
        $schema = $this->connection->createSchemaManager();
        $instance = $schema->introspectTableByUnquotedName('approval_flow_instance');
        $operation = $schema->introspectTableByUnquotedName('approval_flow_operation');
        static::assertTrue($instance->hasIndex('idx.approval_flow_instance.status'));
        static::assertTrue($operation->hasIndex('uniq.approval_flow_operation.version'));
        static::assertContains($instance->getForeignKey('fk.approval_flow_instance.release')->getOnDeleteAction()->value, ['RESTRICT', 'NO ACTION']);
        static::assertContains($operation->getForeignKey('fk.approval_flow_operation.instance')->getOnDeleteAction()->value, ['RESTRICT', 'NO ACTION']);
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->connection->delete('approval_flow_release', ['id' => $releaseId]);
    }
}
