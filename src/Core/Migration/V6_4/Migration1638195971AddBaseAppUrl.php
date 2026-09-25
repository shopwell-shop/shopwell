<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1638195971AddBaseAppUrl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1638195971;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement('ALTER TABLE `app` ADD `base_app_url` VARCHAR(1024) NULL AFTER `version`');
        } catch (\Exception) {
            // Column already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
