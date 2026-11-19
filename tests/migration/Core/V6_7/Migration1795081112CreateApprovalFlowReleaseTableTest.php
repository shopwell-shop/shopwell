<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1795081112CreateApprovalFlowReleaseTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1795081112CreateApprovalFlowReleaseTable::class)]
class Migration1795081112CreateApprovalFlowReleaseTableTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'approval_release_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        foreach (['user', 'approval_flow_definition'] as $table) {
            $this->connection->executeStatement('CREATE TABLE `' . $table . '` LIKE `' . $this->shared->getDatabase() . '`.`' . $table . '`');
        }
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testFreshInstallationCreatesReleaseConstraintsAndCanBeRepeatedWithoutLosingRecords(): void
    {
        $migration = new Migration1795081112CreateApprovalFlowReleaseTable();
        $migration->update($this->connection);
        $definitionId = Uuid::randomBytes();
        $releaseId = Uuid::randomBytes();
        $this->connection->insert('approval_flow_definition', ['id' => $definitionId, 'name' => 'Approval', 'created_at' => '2026-11-19 00:00:00.000']);
        $this->connection->insert('approval_flow_release', [
            'id' => $releaseId,
            'definition_id' => $definitionId,
            'draft_revision' => 1,
            'graph_json' => '{}',
            'contract_json' => '{}',
            'graph_plan_json' => '{}',
            'node_plan_json' => '{}',
            'dependencies_json' => '{}',
            'content_hash' => str_repeat('a', 64),
            'created_at' => '2026-11-19 00:00:00.000',
        ]);

        $migration->update($this->connection);

        static::assertSame($releaseId, $this->connection->fetchOne('SELECT id FROM approval_flow_release'));
        $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName('approval_flow_release');
        static::assertSame(IndexType::UNIQUE, $table->getIndex('uniq.approval_flow_release.content')->getType());
        static::assertSame(['definition_id', 'content_hash'], array_map(static fn ($column): string => $column->getColumnName()->getIdentifier()->getValue(), $table->getIndex('uniq.approval_flow_release.content')->getIndexedColumns()));
        static::assertContains($table->getForeignKey('fk.approval_flow_release.definition')->getOnDeleteAction()->value, ['RESTRICT', 'NO ACTION']);
        static::assertSame('SET NULL', $table->getForeignKey('fk.approval_flow_release.created_by')->getOnDeleteAction()->value);
        static::assertFalse($table->hasColumn('updated_at'));
    }
}
