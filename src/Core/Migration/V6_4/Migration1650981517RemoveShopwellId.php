<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1650981517RemoveShopwellId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1650981517;
    }

    public function update(Connection $connection): void
    {
        // nth
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement(
            'DELETE FROM `system_config` WHERE `configuration_key` = "core.store.shopwellId"'
        );
    }
}
