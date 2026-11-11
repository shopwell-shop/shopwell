/** @sw-package fundamentals@framework */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'system',
    key: 'user',
    roles: {
        viewer: {
            privileges: [
                'user:read',
                'organization_unit:read',
                'position:read',
                'user_organization_unit:read',
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
                'user_organization_unit:create',
                'user_organization_unit:update',
                'user_organization_unit:delete',
                'user_access_key:create',
                'user_access_key:update',
                'user_access_key:delete',
                'system_config:read',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
                'api_action_user_mcp-allowlist',
            ],
            dependencies: [
                'user.viewer',
            ],
        },
        creator: {
            privileges: [
                'user:create',
            ],
            dependencies: [
                'user.viewer',
                'user.editor',
            ],
        },
        deleter: {
            privileges: [
                'user:delete',
            ],
            dependencies: [
                'user.viewer',
            ],
        },
    },
});

/** @private */
export {};
