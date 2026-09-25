<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1558938938ChangeGroupSortingColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1558938938;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` ADD `configurator_group_config` json NULL AFTER `configurator_group_sorting`;');
        $connection->executeStatement('ALTER TABLE `product` DROP COLUMN `configurator_group_sorting`;');
        $connection->executeStatement('ALTER TABLE `product` ADD COLUMN `display_in_listing` TINYINT(1) DEFAULT 1');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
