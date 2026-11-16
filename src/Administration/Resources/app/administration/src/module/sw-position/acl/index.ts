/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'position',
    roles: {
        viewer: {
            privileges: ['position:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['position:update'],
            dependencies: ['position.viewer'],
        },
        creator: {
            privileges: ['position:create'],
            dependencies: ['position.viewer', 'position.editor'],
        },
        deleter: {
            privileges: ['position:delete'],
            dependencies: ['position.viewer'],
        },
    },
});
