/**
 * @sw-package checkout
 */

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'loyalty',
    roles: {
        viewer: {
            privileges: [
                'loyalty_tier:read',
                'customer_group:read',
                'customer_loyalty:read',
                'customer_wallet:read',
                'customer_wallet_transaction:read',
                'currency:read',
                'loyalty.viewer',
                'wallet.viewer',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'loyalty_tier:update',
                'loyalty.editor',
                'wallet.editor',
            ],
            dependencies: ['loyalty.viewer'],
        },
        creator: {
            privileges: ['loyalty_tier:create'],
            dependencies: ['loyalty.viewer', 'loyalty.editor'],
        },
        deleter: {
            privileges: ['loyalty_tier:delete'],
            dependencies: ['loyalty.viewer'],
        },
    },
});

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'wallet',
    roles: {
        viewer: {
            privileges: ['wallet.viewer'],
            dependencies: [],
        },
        editor: {
            privileges: ['wallet.editor'],
            dependencies: ['wallet.viewer'],
        },
    },
});
