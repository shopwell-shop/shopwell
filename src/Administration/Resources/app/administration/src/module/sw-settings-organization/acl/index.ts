/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'organization',
    roles: {
        viewer: {
            privileges: ['organization:read', 'organization_unit:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['organization:update', 'organization_unit:update'],
            dependencies: ['organization.viewer'],
        },
        creator: {
            privileges: ['organization:create', 'organization_unit:create'],
            dependencies: ['organization.viewer', 'organization.editor'],
        },
        deleter: {
            privileges: ['organization:delete', 'organization_unit:delete'],
            dependencies: ['organization.viewer'],
        },
    },
});

