/**
 * @sw-package checkout
 */

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'payment',
    roles: {
        viewer: {
            privileges: [
                'payment_method:read',
                Shopwell.Service('privileges').getPrivileges('media.viewer'),
                'rule:read',
                'plugin:read',
                'system_config:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'app:read',
                'app_payment_method:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['payment_method:update', Shopwell.Service('privileges').getPrivileges('media.creator')],
            dependencies: ['payment.viewer'],
        },
        creator: {
            privileges: ['payment_method:create'],
            dependencies: ['payment.viewer', 'payment.editor'],
        },
        deleter: {
            privileges: [
                'payment_method:delete',
                'customer:read',
                'sales_channel:read',
                'order_transaction:read',
            ],
            dependencies: ['payment.viewer', 'payment.editor'],
        },
    },
});
