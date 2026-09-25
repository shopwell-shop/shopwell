<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1633358879AddUpdateByColumnToImportExportProfile extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1633358879;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `import_export_profile` ADD `update_by` json NULL AFTER `mapping`');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
