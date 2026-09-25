<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1587111506AddPausedScheduleToProductExport extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587111506;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE product_export ADD COLUMN paused_schedule TINYINT(1) NULL DEFAULT \'0\'');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
