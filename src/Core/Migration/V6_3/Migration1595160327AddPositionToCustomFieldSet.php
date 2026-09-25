<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1595160327AddPositionToCustomFieldSet extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1595160327;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `custom_field_set`
            ADD COLUMN `position` INT(11) NOT NULL DEFAULT 1 AFTER `active`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
