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
class Migration1647260673AddIndexForEmail extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1647260673;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'customer', 'idx.email')) {
            return;
        }

        $connection->executeStatement('CREATE INDEX `idx.email` ON `customer` (`email`)');
    }
}
