<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1787311123AddSourceTypeToStateMachineHistory;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1787311123AddSourceTypeToStateMachineHistory::class)]
class Migration1787311123AddSourceTypeToStateMachineHistoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        static::assertSame(1787311123, (new Migration1787311123AddSourceTypeToStateMachineHistory())->getCreationTimestamp());
    }

    public function testMigrationAddsTheColumnAndCanRunTwice(): void
    {
        $this->rollback();

        $migration = new Migration1787311123AddSourceTypeToStateMachineHistory();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'state_machine_history', 'source_type'));
    }

    private function rollback(): void
    {
        if (TableHelper::columnExists($this->connection, 'state_machine_history', 'source_type')) {
            $this->connection->executeStatement('ALTER TABLE `state_machine_history` DROP COLUMN `source_type`;');
        }
    }
}
