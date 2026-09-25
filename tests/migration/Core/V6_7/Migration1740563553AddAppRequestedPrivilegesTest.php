<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_7\Migration1740563553AddAppRequestedPrivileges;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1740563553AddAppRequestedPrivileges::class)]
class Migration1740563553AddAppRequestedPrivilegesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1740563553, (new Migration1740563553AddAppRequestedPrivileges())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropRequestedPrivilegesColumn();

        static::assertFalse(TableHelper::columnExists($this->connection, 'app', 'requested_privileges'));

        $migration = new Migration1740563553AddAppRequestedPrivileges();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'app', 'requested_privileges'));
    }

    private function dropRequestedPrivilegesColumn(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app` DROP COLUMN `requested_privileges`;'
            );
        } catch (\Throwable) {
        }
    }
}
