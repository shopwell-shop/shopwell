<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1773829002RemoveRepairedDigitalProductStatesFlag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1773829002;
    }

    public function update(Connection $connection): void
    {
        $storage = new MySQLKeyValueStorage($connection);
        $storage->remove('core.repaired_digital_product_states');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
