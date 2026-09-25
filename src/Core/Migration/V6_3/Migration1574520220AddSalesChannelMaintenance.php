<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1574520220AddSalesChannelMaintenance extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574520220;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `sales_channel` ADD `maintenance` tinyint(1) NOT NULL DEFAULT 0 AFTER `active`
        ');
        $connection->executeStatement('
            ALTER TABLE `sales_channel` ADD `maintenance_ip_whitelist` JSON NULL AFTER `maintenance`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
