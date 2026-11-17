<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794630767CreateWorkflowRuntime;
use Shopwell\Core\Migration\V6_7\Migration1794838402AddWorkflowResubmission;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794838402AddWorkflowResubmission::class)]
class Migration1794838402AddWorkflowResubmissionTest extends TestCase
{
    private Connection $shared;

    private Connection $connection;

    private string $database;

    protected function setUp(): void
    {
        $this->shared = KernelLifecycleManager::getConnection();
        $this->database = 'workflow_baseline_' . Uuid::randomHex();
        $this->shared->executeStatement('CREATE DATABASE `' . $this->database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->connection = new Connection(array_replace($this->shared->getParams(), ['dbname' => $this->database]), $this->shared->getDriver(), $this->shared->getConfiguration());
        foreach (['user', 'locale', 'language', 'state_machine', 'state_machine_state', 'state_machine_transition', 'state_machine_translation', 'state_machine_state_translation'] as $table) {
            $this->connection->executeStatement('CREATE TABLE `' . $table . '` LIKE `' . $this->shared->getDatabase() . '`.`' . $table . '`');
        }
        foreach (['locale', 'language'] as $table) {
            $this->connection->executeStatement('INSERT INTO `' . $table . '` SELECT * FROM `' . $this->shared->getDatabase() . '`.`' . $table . '`');
        }
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->shared->executeStatement('DROP DATABASE `' . $this->database . '`');
    }

    public function testMigrationAddsRoundAwareReceiptsAndNonterminalReturnTransitions(): void
    {
        (new Migration1794630767CreateWorkflowRuntime())->update($this->connection);
        $migration = new Migration1794838402AddWorkflowResubmission();
        $migration->update($this->connection);
        $states = $this->connection->fetchAllAssociative('SELECT id, technical_name FROM state_machine_state ORDER BY id');
        $migration->update($this->connection);
        static::assertSame($states, $this->connection->fetchAllAssociative('SELECT id, technical_name FROM state_machine_state ORDER BY id'));
        $schema = $this->connection->createSchemaManager();
        static::assertTrue($schema->introspectTableByUnquotedName('workflow_task_history')->hasColumn('round'));
        $copy = $schema->introspectTableByUnquotedName('workflow_copy');
        static::assertSame(['instance_id', 'round', 'node_id', 'user_id'], array_map(static fn ($column): string => $column->getColumnName()->getIdentifier()->getValue(), $copy->getIndex('uniq.workflow_copy.round_node_user')->getIndexedColumns()));
        static::assertSame(IndexType::UNIQUE, $copy->getIndex('uniq.workflow_copy.round_node_user')->getType());
        $transitions = $this->connection->fetchAllAssociative('SELECT t.action_name action, f.technical_name source, s.technical_name target FROM state_machine_transition t INNER JOIN state_machine_state f ON f.id = t.from_state_id INNER JOIN state_machine_state s ON s.id = t.to_state_id WHERE f.technical_name = \'returned\' OR s.technical_name = \'returned\' ORDER BY action');
        static::assertSame([
            ['action' => 'cancel', 'source' => 'returned', 'target' => 'canceled'],
            ['action' => 'resubmit', 'source' => 'returned', 'target' => 'running'],
            ['action' => 'return', 'source' => 'running', 'target' => 'returned'],
        ], $transitions);
    }
}
