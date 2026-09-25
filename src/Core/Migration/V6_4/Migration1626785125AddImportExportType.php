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
class Migration1626785125AddImportExportType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1626785125;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'import_export_profile', 'type')) {
            $connection->executeStatement(
                'ALTER TABLE import_export_profile
            ADD COLUMN type varchar(255) NOT NULL DEFAULT "import-export" AFTER `enclosure`'
            );
        }
    }
}
