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
class Migration1653385302AddHeadlineColumnToAppFlowActionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1653385302;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'app_flow_action_translation', 'headline')) {
            $connection->executeStatement('ALTER TABLE `app_flow_action_translation` ADD `headline` VARCHAR(255) NULL AFTER `description`;');
        }

        $connection->executeStatement('
            ALTER TABLE `app_flow_action_translation`
                MODIFY COLUMN `description` LONGTEXT NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
