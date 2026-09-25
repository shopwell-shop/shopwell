<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1562306893MakeCustomerFirstLoginDateTime extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562306893;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer`
            MODIFY COLUMN `first_login` DATETIME(3) NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
