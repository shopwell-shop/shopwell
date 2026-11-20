<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1795141719CreateApprovalFlowBindingTables;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795141719CreateApprovalFlowBindingTables::class)]
class Migration1795141719CreateApprovalFlowBindingTablesTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    private string $releaseId;

    private string $bindingId;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'approval_binding_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        $this->connection->executeStatement('CREATE TABLE approval_flow_release (id BINARY(16) NOT NULL PRIMARY KEY) ENGINE = InnoDB');
        $this->connection->executeStatement('CREATE TABLE user (id BINARY(16) NOT NULL PRIMARY KEY) ENGINE = InnoDB');
        (new Migration1795141719CreateApprovalFlowBindingTables())->update($this->connection);
        $this->releaseId = Uuid::randomBytes();
        $this->bindingId = Uuid::randomBytes();
        $this->connection->insert('approval_flow_release', ['id' => $this->releaseId]);
        $this->connection->insert('approval_flow_binding', $this->bindingRow($this->bindingId));
        $this->connection->insert('approval_flow_binding_head', [
            'id' => Uuid::randomBytes(), 'business_type' => 'payment:transfer',
            'latest_revision' => 1, 'active_revision' => 1, 'version' => 1, 'created_at' => '2026-11-20 00:00:00.123',
        ]);
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testRepeatedMigrationRetainsTheBindingAndItsActiveSelection(): void
    {
        $migration = new Migration1795141719CreateApprovalFlowBindingTables();
        $migration->update($this->connection);

        static::assertSame(1795141719, $migration->getCreationTimestamp());
        static::assertSame($this->bindingId, $this->connection->fetchOne('SELECT id FROM approval_flow_binding'));
        static::assertSame('{"$id":"payment:transfer","type":"object"}', $this->connection->fetchOne('SELECT form_schema FROM approval_flow_binding'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT active_revision FROM approval_flow_binding_head'));
        static::assertSame(1, (int) $this->connection->fetchOne('SELECT version FROM approval_flow_binding_head'));
        $schema = $this->connection->createSchemaManager();
        static::assertTrue($schema->introspectTableByUnquotedName('approval_flow_binding')->hasIndex('uniq.approval_flow_binding.revision'));
        static::assertTrue($schema->introspectTableByUnquotedName('approval_flow_binding_head')->hasForeignKey('fk.approval_flow_binding_head.active'));
    }

    public function testARevisionCannotBeAllocatedTwice(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $this->connection->insert('approval_flow_binding', $this->bindingRow(Uuid::randomBytes()));
    }

    public function testTheHeadCannotSelectAnotherBusinessTypesRevision(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->connection->insert('approval_flow_binding_head', [
            'id' => Uuid::randomBytes(), 'business_type' => 'expense:claim',
            'latest_revision' => 1, 'active_revision' => 1, 'version' => 1, 'created_at' => '2026-11-20 00:00:00.123',
        ]);
    }

    public function testAReferencedReleaseCannotBeDeleted(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->connection->delete('approval_flow_release', ['id' => $this->releaseId]);
    }

    public function testTheActiveBindingCannotBeDeleted(): void
    {
        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->connection->delete('approval_flow_binding', ['id' => $this->bindingId]);
    }

    /**
     * @return array<string, int|string>
     */
    private function bindingRow(string $id): array
    {
        return [
            'id' => $id, 'business_type' => 'payment:transfer', 'business_version' => '1.0.0',
            'revision' => 1, 'release_id' => $this->releaseId, 'form_schema_id' => 'payment:transfer',
            'form_schema' => '{"$id":"payment:transfer","type":"object"}', 'form_schema_hash' => str_repeat('a', 64),
            'created_at' => '2026-11-20 00:00:00.123',
        ];
    }
}
