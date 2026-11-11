/** @sw-package fundamentals@framework */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'system',
    key: 'permissions',
    roles: {
        viewer: {
            privileges: [
                'acl_role:read',
                'user:read',
                'integration:read',
                'app:read',
                'admin_menu_item:read',
                'admin_menu_item_role:read',
                'api_acl_privileges_additional_get',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'acl_role:update',
                'admin_menu_item_role:create',
                'admin_menu_item_role:delete',
            ],
            dependencies: [
                'permissions.viewer',
            ],
        },
        creator: {
            privileges: [
                'acl_role:create',
            ],
            dependencies: [
                'permissions.viewer',
                'permissions.editor',
            ],
        },
        deleter: {
            privileges: [
                'acl_role:delete',
            ],
            dependencies: [
                'permissions.viewer',
            ],
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
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'admin_menu_item:update',
            ],
            dependencies: [
                'admin_menu.viewer',
            ],
        },
        creator: {
            privileges: [
                'admin_menu_item:create',
            ],
            dependencies: [
                'admin_menu.viewer',
                'admin_menu.editor',
            ],
        },
        deleter: {
            privileges: [
                'admin_menu_item:delete',
            ],
            dependencies: [
                'admin_menu.viewer',
            ],
        },
    },
});

/** @private */
export {};
