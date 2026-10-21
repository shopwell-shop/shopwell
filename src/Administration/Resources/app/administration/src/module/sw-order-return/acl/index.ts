/**
 * @sw-package after-sales
 *
 * The privileges of the returns.
 *
 * The group hangs below `orders` and not below `settings`: a return is decided by the same person
 * that reads the order it belongs to, so a role that may not see orders can not be given returns
 * either - the two are one job. The settings page of the module uses the same roles, because the
 * reasons it maintains are only the vocabulary a return is opened with.
 *
 * `viewer` reads and `editor` decides. There is deliberately no separate "refunder": a refund is one
 * of the decisions on a return, and a role that may receive goods but not refund them would leave the
 * goods in the warehouse and the money in the shop.
 */

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'orders',
    key: 'order_return',
    roles: {
        viewer: {
            privileges: [
                'order_return:read',
                'order_return_line_item:read',
                'order_return_message:read',
                'order_return_reason:read',
                'order:read',
                'customer:read',
                'state_machine_state:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'order_return:update',
                'order_return_message:create',
                'order_return_reason:create',
                'order_return_reason:update',
                'order_return_reason:delete',
            ],
            dependencies: ['order_return.viewer'],
        },
    },
});
