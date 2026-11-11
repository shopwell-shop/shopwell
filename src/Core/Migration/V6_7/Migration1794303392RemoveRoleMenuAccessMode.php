<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class Migration1794303392RemoveRoleMenuAccessMode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794303392;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'acl_role', 'menu_access_mode');
    }
}
