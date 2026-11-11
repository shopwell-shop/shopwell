<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class Migration1794296487RoleMenuAccess extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794296487;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'acl_role', 'menu_access_mode', 'VARCHAR(16)', nullable: false, default: '\'auto\'');
        $this->splitRolePrivileges($connection);
        $this->renameMenuReferences($connection);
        $userRead = ['organization_unit:read', 'position:read', 'user_organization_unit:read'];
        $userAssignments = [...$userRead, 'user_organization_unit:create', 'user_organization_unit:update', 'user_organization_unit:delete'];
        $this->addAdditionalPrivileges($connection, [
            'user.viewer' => $userRead,
            'user.editor' => $userAssignments,
            'user.creator' => $userAssignments,
            'user.deleter' => $userRead,
            'permissions.viewer' => ['admin_menu_item:read', 'admin_menu_item_role:read'],
            'permissions.editor' => ['admin_menu_item:read', 'admin_menu_item_role:read', 'admin_menu_item_role:create', 'admin_menu_item_role:delete'],
            'permissions.creator' => ['admin_menu_item:read', 'admin_menu_item_role:read', 'admin_menu_item_role:create', 'admin_menu_item_role:delete'],
        ]);
    }

    private function splitRolePrivileges(Connection $connection): void
    {
        foreach ($connection->fetchAllAssociative('SELECT id, privileges FROM acl_role') as $role) {
            $privileges = json_decode($role['privileges'], true, flags: \JSON_THROW_ON_ERROR);
            $updated = [];
            foreach ($privileges as $privilege) {
                if (str_starts_with($privilege, 'users_and_permissions.')) {
                    $suffix = substr($privilege, \strlen('users_and_permissions.'));
                    $updated[] = 'user.' . $suffix;
                    $updated[] = 'permissions.' . $suffix;
                } else {
                    $updated[] = $privilege;
                }
            }
            if ($updated !== $privileges) {
                $connection->update('acl_role', ['privileges' => json_encode(array_values(array_unique($updated)), \JSON_THROW_ON_ERROR)], ['id' => $role['id']]);
            }
        }
    }

    private function renameMenuReferences(Connection $connection): void
    {
        foreach (['sw-system-user' => 'sw-user', 'sw-system-role' => 'sw-permissions-role', 'sw-system-menu' => 'sw-permissions-menu'] as $old => $new) {
            $connection->executeStatement('UPDATE admin_menu_item SET internal_key = :new WHERE internal_key = :old', ['old' => $old, 'new' => $new]);
            $connection->executeStatement('UPDATE admin_menu_item SET route_key = :new WHERE route_key = :old', ['old' => $old, 'new' => $new]);
        }
        foreach ([
            'sw-system.general.mainMenuItemSystem' => 'global.sw-admin-menu.navigation.mainMenuItemSystem',
            'sw-system.navigation.user' => 'sw-user.general.label',
            'sw-system.navigation.role' => 'sw-permissions.roles.general.label',
            'sw-system.navigation.menu' => 'sw-permissions.menu.headline',
        ] as $old => $new) {
            $connection->executeStatement('UPDATE admin_menu_item_translation SET title = :new WHERE title = :old', ['old' => $old, 'new' => $new]);
        }
        $connection->executeStatement('UPDATE admin_menu_item_translation SET title = REPLACE(title, \'sw-system.menu.\', \'sw-permissions.menu.\') WHERE title LIKE \'sw-system.menu.%\'');
        $connection->executeStatement('UPDATE admin_menu_item SET privilege = REPLACE(privilege, \'users_and_permissions.\', \'permissions.\') WHERE privilege LIKE \'users_and_permissions.%\'');
        $connection->executeStatement('UPDATE admin_menu_item SET privilege = \'user.viewer\' WHERE internal_key IN (\'sw-user\', \'sw-settings-user-permission\') AND privilege = \'permissions.viewer\'');
    }
}
