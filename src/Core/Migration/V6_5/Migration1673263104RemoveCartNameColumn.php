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
class Migration1673263104RemoveCartNameColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673263104;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'cart', 'name')) {
            return;
        }

        $column = TableHelper::getColumnOfTable($connection, 'cart', 'name');

        if ($column->isNotNull) {
            $connection->executeStatement(
                'ALTER TABLE `cart` CHANGE `name` `name` VARCHAR(500) COLLATE utf8mb4_unicode_ci'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'cart', 'name');
    }
}
