/**
 * @sw-package checkout
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    key: 'sw-payments',
    parent: 'settings',
    roles: {
        viewer: {
            privileges: [Shopwell.Service('privileges').getPrivileges('payment.viewer'), 'app.ShopwellPayments'],
            dependencies: ['app.ShopwellPayments'],
        },
        editor: {
            privileges: [],
            dependencies: ['sw-payments.viewer'],
        },
        creator: {
            privileges: [],
            dependencies: ['sw-payments.viewer', 'sw-payments.editor'],
        },
    },
});
