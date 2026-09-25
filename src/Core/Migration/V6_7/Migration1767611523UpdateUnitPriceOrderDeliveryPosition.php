<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1767611523UpdateUnitPriceOrderDeliveryPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1767611523;
    }

    public function update(Connection $connection): void
    {
        $columnType = TableHelper::getColumnOfTable($connection, 'order_delivery_position', 'unit_price')->type;
        if ($columnType === Types::INTEGER) {
            $connection->executeStatement('
                ALTER TABLE `order_delivery_position`
                MODIFY `unit_price` DOUBLE
                GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`price`, \'$.unitPrice\'))) VIRTUAL
        ');
        }
    }
}
