/**
 * @sw-package checkout
 */

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'wallet',
    roles: {
        viewer: {
            privileges: [
                'customer_wallet:read',
                'customer_wallet_transaction:read',
                'customer_wallet_top_up:read',
                'wallet.viewer',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['wallet.editor'],
            dependencies: ['wallet.viewer'],
        },
    },
});
