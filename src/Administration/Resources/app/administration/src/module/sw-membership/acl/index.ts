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
                'loyalty_account:read',
                'loyalty_point_transaction:read',
                'membership_plan:read',
                'membership_purchase:read',
                'currency:read',
                'loyalty.viewer',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'loyalty_tier:update',
                'membership_plan:update',
                'loyalty.editor',
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
