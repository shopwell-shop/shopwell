<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;
use Shopwell\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class Migration1666689977AddPluginIdToCustomEntity extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1666689977;
    }

    public function update(Connection $connection): void
    {
        if (!TableHelper::columnExists($connection, 'custom_entity', 'plugin_id')) {
            $connection->executeStatement('ALTER TABLE `custom_entity`
                ADD `plugin_id` BINARY(16) NULL,
                ADD CONSTRAINT `fk.custom_entity.plugin_id`
                    FOREIGN KEY (`plugin_id`)
                    REFERENCES `plugin` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE');
        }
    }
}
