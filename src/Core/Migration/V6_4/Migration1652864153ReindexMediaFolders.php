<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1652864153ReindexMediaFolders extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1652864153;
    }

    public function update(Connection $connection): void
    {
        if ($this->isInstallation()) {
            return;
        }

        $this->registerIndexer($connection, 'media_folder.indexer', [MediaFolderIndexer::CHILD_COUNT_UPDATER]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
