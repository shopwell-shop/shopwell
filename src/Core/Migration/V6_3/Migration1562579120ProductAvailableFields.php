<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1562579120ProductAvailableFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562579120;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` CHANGE `stock` `stock` int(11) NOT NULL AFTER `ean`;');
        $connection->executeStatement('ALTER TABLE `product` ADD `available_stock` int(11) NULL AFTER `stock`;');
        $connection->executeStatement('ALTER TABLE `product` ADD `available` tinyint(1) NOT NULL DEFAULT 1 AFTER `available_stock`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
