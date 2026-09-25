<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1752750234AddIndexToOrderTransactionCreateAndUpdate;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1752750234AddIndexToOrderTransactionCreateAndUpdate::class)]
class Migration1752750234AddIndexToOrderTransactionCreateAndUpdateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1752750234, (new Migration1752750234AddIndexToOrderTransactionCreateAndUpdate())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1752750234AddIndexToOrderTransactionCreateAndUpdate();
        static::assertSame(1752750234, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1752750234AddIndexToOrderTransactionCreateAndUpdate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'order_transaction', 'idx.order_transaction_created_updated'));
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists($this->connection, 'order_transaction', 'idx.order_transaction_created_updated')) {
            $this->connection->executeStatement('DROP INDEX `idx.order_transaction_created_updated` ON `order_transaction`');
        }
    }
}
