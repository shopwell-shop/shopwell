<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_6;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Util\Database\TableHelper;
use Shopwell\Core\Migration\V6_6\Migration1676367607RemoveIntegrationWriteAccessColumn;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1676367607RemoveIntegrationWriteAccessColumn::class)]
class Migration1676367607RemoveIntegrationWriteAccessColumnTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1676367607, (new Migration1676367607RemoveIntegrationWriteAccessColumn())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $existed = TableHelper::columnExists($connection, 'integration', 'write_access');

        if (!$existed) {
            $connection->executeStatement('
                ALTER TABLE `integration` ADD COLUMN `write_access` TINYINT(1) DEFAULT 0
            ');
        }

        $migration = new Migration1676367607RemoveIntegrationWriteAccessColumn();

        $migration->updateDestructive($connection);
        $migration->updateDestructive($connection);

        static::assertFalse(TableHelper::columnExists($connection, 'integration', 'write_access'));

        if ($existed) {
            $connection->executeStatement('
                ALTER TABLE `integration` ADD COLUMN `write_access` TINYINT(1) DEFAULT 0
            ');
        }
    }
}
