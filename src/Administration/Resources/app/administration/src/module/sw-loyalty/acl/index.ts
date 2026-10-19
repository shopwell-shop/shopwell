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
                'customer_wallet_top_up:read',
                'customer_point_transaction:read',
                'membership_plan:read',
                'membership_order:read',
                'currency:read',
                'loyalty.viewer',
                'wallet.viewer',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'loyalty_tier:update',
                'membership_plan:update',
                'loyalty.editor',
                'wallet.editor',
            ],
            dependencies: ['loyalty.viewer'],
        },
        creator: {
            privileges: ['loyalty_tier:create', 'membership_plan:create'],
            dependencies: ['loyalty.viewer', 'loyalty.editor'],
        },
        deleter: {
            privileges: ['loyalty_tier:delete', 'membership_plan:delete'],
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
