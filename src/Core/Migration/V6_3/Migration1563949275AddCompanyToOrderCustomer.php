<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1563949275AddCompanyToOrderCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563949275;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `order_customer` ADD `company` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL AFTER `title`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
