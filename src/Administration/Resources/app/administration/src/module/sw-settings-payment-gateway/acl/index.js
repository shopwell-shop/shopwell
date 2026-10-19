/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'payment_app',
    roles: {
        viewer: {
            privileges: ['payment_app:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['payment_app:update'],
            dependencies: ['payment_app.viewer'],
        },
        creator: {
            privileges: ['payment_app:create'],
            dependencies: ['payment_app.viewer', 'payment_app.editor'],
        },
        deleter: {
            privileges: ['payment_app:delete'],
            dependencies: ['payment_app.viewer'],
        },
    },
});
