/**
 * @sw-package fundamentals@framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    // The privilege keys stay `users_and_permissions.*`, only the group moves: they are stored
    // in `acl_role.privileges` of existing roles and renaming them would silently drop grants.
    parent: 'system',
    key: 'users_and_permissions',
    roles: {
        viewer: {
            privileges: [
                'user:read',
                'acl_role:read',
                'user_access_key:read',
                'media:read',
                'media_default_folder:read',
                'integration:read',
                'app:read',
                'user_config:read',
                'user_config:create',
                'user_config:update',
                'currency:read',
                'system_config:read',
                'api_acl_privileges_additional_get',
                'media_folder:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'user:update',
                'acl_role:update',
                'user_access_key:create',
                'user_access_key:update',
                'user_access_key:delete',
                'system_config:read',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
                'api_action_user_mcp-allowlist',
            ],
            dependencies: ['users_and_permissions.viewer'],
        },
        creator: {
            privileges: ['user:create', 'acl_role:create'],
            dependencies: ['users_and_permissions.viewer', 'users_and_permissions.editor'],
        },
        deleter: {
            privileges: ['user:delete', 'acl_role:delete'],
            dependencies: ['users_and_permissions.viewer'],
        },
    },
});

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'system',
    key: 'admin_menu',
    roles: {
        viewer: {
            privileges: [
                'admin_menu_item:read',
                'acl_role:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'admin_menu_item:update',
                'admin_menu_item_role:create',
                'admin_menu_item_role:delete',
            ],
            dependencies: ['admin_menu.viewer'],
        },
        creator: {
            privileges: [
                'admin_menu_item:create',
            ],
            dependencies: ['admin_menu.viewer', 'admin_menu.editor'],
        },
        deleter: {
            privileges: [
                'admin_menu_item:delete',
            ],
            dependencies: ['admin_menu.viewer'],
        },
    },
});
