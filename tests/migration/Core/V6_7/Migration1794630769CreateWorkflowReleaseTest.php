<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794630769CreateWorkflowRelease;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794630769CreateWorkflowRelease::class)]
class Migration1794630769CreateWorkflowReleaseTest extends TestCase
{
    public function testFreshCreationAndRepeatedMigrationPreserveExistingRecords(): void
    {
        $shared = KernelLifecycleManager::getConnection();
        $database = 'workflow_table_' . Uuid::randomHex();
        $shared->executeStatement('CREATE DATABASE `' . $database . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $connection = new Connection(array_replace($shared->getParams(), ['dbname' => $database]), $shared->getDriver(), $shared->getConfiguration());
        try {
            $migration = new Migration1794630769CreateWorkflowRelease();
            static::assertSame(1794630769, $migration->getCreationTimestamp());
            $migration->update($connection);
            static::assertTrue($connection->createSchemaManager()->tablesExist(['workflow_release']));
            $record = array_merge(['id' => Uuid::randomBytes(), 'created_at' => '2026-11-14 00:00:00.000'], ['draft_id' => Uuid::randomBytes(), 'revision' => 3, 'name' => 'Published snapshot', 'publisher' => 'core:approval', 'published_by' => Uuid::randomBytes(), 'document' => '{}']);
            $connection->insert('workflow_release', $record);
            $before = $connection->fetchAssociative('SELECT * FROM workflow_release');
            $migration->update($connection);
            static::assertSame($before, $connection->fetchAssociative('SELECT * FROM workflow_release'));
            static::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM workflow_release'));
        } finally {
            $connection->close();
            $shared->executeStatement('DROP DATABASE `' . $database . '`');
        }
    }
}
