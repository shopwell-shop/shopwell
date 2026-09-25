<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1742199552SalesChannelMeasurementUnits extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1742199552;
    }

    public function update(Connection $connection): void
    {
        $this->addMeasurementUnitsColumn($connection);
    }

    private function addMeasurementUnitsColumn(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'sales_channel', 'measurement_units')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `sales_channel`
            ADD COLUMN `measurement_units` JSON NULL;
        ');
    }
}
