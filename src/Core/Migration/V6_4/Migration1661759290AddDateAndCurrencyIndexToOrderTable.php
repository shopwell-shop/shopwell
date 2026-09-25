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
class Migration1661759290AddDateAndCurrencyIndexToOrderTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1661759290;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'order', 'idx.order_date_currency_id')) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `order` ADD INDEX `idx.order_date_currency_id` (`order_date`, `currency_id`)
        ');
    }
}
