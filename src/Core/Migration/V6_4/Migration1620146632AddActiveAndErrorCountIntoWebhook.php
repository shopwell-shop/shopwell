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
class Migration1620146632AddActiveAndErrorCountIntoWebhook extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620146632;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'webhook', 'active')) {
            $connection->executeStatement('ALTER TABLE `webhook` ADD COLUMN `active` TINYINT(1) DEFAULT 1 AFTER `app_id`');
        }

        if (!TableHelper::columnExists($connection, 'webhook', 'error_count')) {
            $connection->executeStatement('ALTER TABLE `webhook` ADD COLUMN `error_count` INT(11) NOT NULL DEFAULT 0');
        }
    }
}
