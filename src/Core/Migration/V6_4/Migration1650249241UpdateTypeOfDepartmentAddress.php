<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1650249241UpdateTypeOfDepartmentAddress extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1650249241;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `customer_address`
                MODIFY COLUMN `department` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');

        $connection->executeStatement('
            ALTER TABLE `order_address`
                MODIFY COLUMN `department` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
