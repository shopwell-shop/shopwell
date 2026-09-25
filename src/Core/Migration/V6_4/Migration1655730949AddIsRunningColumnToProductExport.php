<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1655730949AddIsRunningColumnToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1655730949;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'product_export', 'is_running')) {
            return;
        }

        $sql = <<<'SQL'
            ALTER TABLE `product_export`
            ADD COLUMN `is_running` TINYINT(1) NOT NULL DEFAULT 0
        SQL;

        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Implement updateDestructive() method.
    }
}
