/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'oauth2_client',
    roles: {
        viewer: {
            privileges: ['oauth2_client:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['oauth2_client:update'],
            dependencies: ['oauth2_client.viewer'],
        },
        creator: {
            privileges: ['oauth2_client:create'],
            dependencies: ['oauth2_client.viewer', 'oauth2_client.editor'],
        },
        deleter: {
            privileges: ['oauth2_client:delete'],
            dependencies: ['oauth2_client.viewer'],
        },
    },
});
