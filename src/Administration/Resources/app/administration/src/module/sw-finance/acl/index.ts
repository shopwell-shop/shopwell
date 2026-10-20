/**
 * @sw-package framework
 *
 * The privileges of the finance pages.
 *
 * The transfer roles (`payment_transfer.viewer` / `creator` / `approver`) are declared next to the
 * entity they belong to, in `sw-payment/acl`: the payment gateway writes those rows, the finance module
 * only decides who may look at them and who may release them. What is declared here is the wallet,
 * because the page that reads the top ups moved here.
 *
 * `parent: 'settings'` is deliberately unchanged: the group is already used by existing roles, and
 * moving it to another group in the role page would only change where it is displayed.
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
