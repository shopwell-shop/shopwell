<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1720094362AddStateForeignKeyToOrderDelivery extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720094362;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            UPDATE `order_delivery`
            SET `state_id` = (SELECT `initial_state_id` FROM `state_machine` WHERE `technical_name` = 'order_delivery.state')
            WHERE `state_id` NOT IN (SELECT `id` FROM `state_machine_state` WHERE `state_machine_id` = (SELECT `id` FROM `state_machine` WHERE `technical_name` = 'order_delivery.state'));
        SQL);

        if (TableHelper::foreignKeyExistsByColumns($connection, 'order_delivery', ['state_id'], 'state_machine_state', ['id'])) {
            return;
        }

        $connection->executeStatement(<<<'SQL'
            ALTER TABLE `order_delivery`
            ADD CONSTRAINT `fk.order_delivery.state_id` FOREIGN KEY (`state_id`)
            REFERENCES `state_machine_state` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
        SQL);
    }
}
