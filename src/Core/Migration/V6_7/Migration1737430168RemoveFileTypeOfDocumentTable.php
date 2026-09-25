<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1737430168RemoveFileTypeOfDocumentTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1737430168;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `document` CHANGE COLUMN `file_type` `file_type` VARCHAR(255) NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'document', 'file_type');
    }
}
