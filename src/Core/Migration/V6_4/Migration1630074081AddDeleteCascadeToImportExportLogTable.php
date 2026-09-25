<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1630074081AddDeleteCascadeToImportExportLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1630074081;
    }

    public function update(Connection $connection): void
    {
        $this->dropForeignKeyIfExists($connection, 'import_export_log', 'fk.import_export_log.file_id');
        $connection->executeStatement('ALTER TABLE `import_export_log` ADD CONSTRAINT `fk.import_export_log.file_id` FOREIGN KEY (`file_id`) REFERENCES `import_export_file` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;');
    }
}
