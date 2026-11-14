<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794630768CreateWorkflowDraft;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794630768CreateWorkflowDraft::class)]
class Migration1794630768CreateWorkflowDraftTest extends TestCase
{
    public function testFreshCreationAndRepeatedMigrationPreserveExistingRecords(): void
    {
        $shared = KernelLifecycleManager::getConnection();
        $database = 'workflow_table_' . Uuid::randomHex();
        $shared->executeStatement('CREATE DATABASE `' . $database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection = new Connection(array_replace($shared->getParams(), ['dbname' => $database]), $shared->getDriver(), $shared->getConfiguration());
        try {
            $migration = new Migration1794630768CreateWorkflowDraft();
            static::assertSame(1794630768, $migration->getCreationTimestamp());
            $migration->update($connection);
            static::assertTrue($connection->createSchemaManager()->tablesExist(['workflow_draft']));
            $record = array_merge(['id' => Uuid::randomBytes(), 'created_at' => '2026-11-14 00:00:00.000'], ['name' => 'Merchant draft', 'revision' => 3, 'document' => '{}']);
            $connection->insert('workflow_draft', $record);
            $before = $connection->fetchAssociative('SELECT * FROM workflow_draft');
            $migration->update($connection);
            static::assertSame($before, $connection->fetchAssociative('SELECT * FROM workflow_draft'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM workflow_draft'));
        } finally {
            $connection->close();
            $shared->executeStatement('DROP DATABASE `' . $database . '`');
        }
    }
}
