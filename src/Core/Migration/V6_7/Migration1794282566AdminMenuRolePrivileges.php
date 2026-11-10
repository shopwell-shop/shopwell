<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class Migration1794282566AdminMenuRolePrivileges extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794282566;
    }

    public function update(Connection $connection): void
    {
        $this->addAdditionalPrivileges($connection, [
            'admin_menu.editor' => ['admin_menu_item_role:create', 'admin_menu_item_role:delete'],
            'admin_menu.creator' => ['admin_menu_item_role:create', 'admin_menu_item_role:delete'],
        ]);
    }
}
