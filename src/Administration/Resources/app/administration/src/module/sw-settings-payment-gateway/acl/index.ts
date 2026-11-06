/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'payment_app',
    roles: {
        viewer: {
            privileges: ['payment_app:read', 'payment_cashier_config:read'],
            dependencies: [],
        },
        editor: {
            privileges: [
                'payment_app:update',
                'payment_cashier_config:create',
                'payment_cashier_config:update',
                'payment_cashier_config:delete',
            ],
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
