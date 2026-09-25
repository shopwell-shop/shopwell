<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_6\Migration1733745893createTagStorageTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1733745893createTagStorageTable::class)]
class Migration1733745893createTagStorageTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `invalidation_tags`;');
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1733745893createTagStorageTable();
        static::assertSame(1733745893, $migration->getCreationTimestamp());
    }

    public function testTableIsCreated(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'invalidation_tags'));

        $migration = new Migration1733745893createTagStorageTable();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'invalidation_tags'));

        static::assertTrue(TableHelper::columnExists($this->connection, 'invalidation_tags', 'tag'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'invalidation_tags', 'id'));
    }
}
