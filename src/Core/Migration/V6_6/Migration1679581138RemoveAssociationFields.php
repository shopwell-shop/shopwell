<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1679581138RemoveAssociationFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1679581138;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'media_default_folder', 'association_fields')) {
            $connection->executeStatement('ALTER TABLE `media_default_folder` CHANGE `association_fields` `association_fields` JSON NULL');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'media_default_folder', 'association_fields');
    }
}
