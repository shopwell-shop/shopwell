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
class Migration1646125417AddPathFieldToMediaFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1646125417;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'media_folder', 'path')) {
            $connection->executeStatement('ALTER TABLE `media_folder` ADD `path` longtext NULL AFTER `child_count`;');
        }

        $this->registerIndexer($connection, 'media_folder.indexer');
    }
}
