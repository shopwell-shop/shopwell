<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1589178550AddTaxCalculationType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1589178550;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `sales_channel` ADD `tax_calculation_type` varchar(50) NOT NULL DEFAULT \'horizontal\' AFTER analytics_id');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
