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
class Migration1648031636AddPositionFieldToShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1648031636;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'shipping_method', 'position')) {
            $connection->executeStatement('ALTER TABLE `shipping_method` ADD `position` INT(11) NOT NULL DEFAULT 1 AFTER `active`;');
        }
    }
}
