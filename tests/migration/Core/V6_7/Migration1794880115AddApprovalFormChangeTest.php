<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794880115AddApprovalFormChange;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794880115AddApprovalFormChange::class)]
class Migration1794880115AddApprovalFormChangeTest extends TestCase
{
    public function testCreatesPrivateRevisionJournalIdempotently(): void
    {
        $shared = KernelLifecycleManager::getConnection();
        $database = 'approval_form_migration_' . Uuid::randomHex();
        $shared->executeStatement('CREATE DATABASE `' . $database . '`');
        $connection = new Connection(array_replace($shared->getParams(), ['dbname' => $database]), $shared->getDriver(), $shared->getConfiguration());
        try {
            foreach (['user', 'workflow_instance', 'workflow_task'] as $table) {
                $connection->executeStatement('CREATE TABLE `' . $table . '` LIKE `' . $shared->getDatabase() . '`.`' . $table . '`');
            }
            $migration = new Migration1794880115AddApprovalFormChange();
            $migration->update($connection);
            $migration->update($connection);
            $table = $connection->createSchemaManager()->introspectTableByUnquotedName('workflow_approval_form_change');
            static::assertTrue($table->hasIndex('uniq.workflow_approval_form_change.revision'));
            static::assertCount(3, $table->getForeignKeys());
            static::assertTrue($table->hasColumn('changes'));
            static::assertFalse($table->hasColumn('updated_at'));
        } finally {
            $connection->close();
            $shared->executeStatement('DROP DATABASE `' . $database . '`');
        }
    }
}
