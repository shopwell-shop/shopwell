<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794846336AddApprovalReminder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794846336AddApprovalReminder::class)]
class Migration1794846336AddApprovalReminderTest extends TestCase
{
    public function testCreatesIndexedTaskReceiptsIdempotently(): void
    {
        $shared = KernelLifecycleManager::getConnection();
        $database = 'reminder_migration_' . Uuid::randomHex();
        $shared->executeStatement('CREATE DATABASE `' . $database . '`');
        $connection = new Connection(array_replace($shared->getParams(), ['dbname' => $database]), $shared->getDriver(), $shared->getConfiguration());
        try {
            foreach (['workflow_instance', 'workflow_task'] as $table) {
                $connection->executeStatement('CREATE TABLE `' . $table . '` LIKE `' . $shared->getDatabase() . '`.`' . $table . '`');
            }
            $migration = new Migration1794846336AddApprovalReminder();
            $migration->update($connection);
            $migration->update($connection);
            $table = $connection->createSchemaManager()->introspectTableByUnquotedName('workflow_approval_reminder');
            static::assertTrue($table->hasIndex('idx.workflow_approval_reminder.due'));
            static::assertTrue($table->hasIndex('idx.workflow_approval_reminder.delivery'));
            static::assertTrue($table->hasIndex('uniq.workflow_approval_reminder.task'));
            static::assertCount(2, $table->getForeignKeys());
            static::assertTrue($table->hasColumn('delivered_at'));
        } finally {
            $connection->close();
            $shared->executeStatement('DROP DATABASE `' . $database . '`');
        }
    }
}
