<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1578590702AddedPropertyGroupPosition extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1578590702;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `property_group_translation` ADD `position` INT(11) NULL DEFAULT 1 AFTER `description`;');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
