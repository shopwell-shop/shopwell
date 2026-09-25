<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1670090989AddIndexOrderOrderNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1670090989;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'order', 'idx.order_number')) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `order` ADD INDEX `idx.order_number` (`order_number`)'
        );
    }
}
