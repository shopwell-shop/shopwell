/**
 * @sw-package checkout
 *
 * The membership orders module: every membership a customer bought, one row per purchase attempt.
 *
 * A membership purchase is an order the customer placed, so the queue sits next to the sales orders
 * instead of in the settings: an operator looking for "the membership this customer paid for" starts
 * at the orders, and the settings (`sw-settings-membership`) are where the tiers and the plans are
 * maintained.
 *
 * The list is read only on purpose: a membership changes through the payment or through the customer
 * detail page, never by editing the purchase row itself.
 */
const { Module } = Shopwell;

// The page is registered lazily and never exported: the module is the only entry point, so a plugin
// must extend it through its twig blocks instead of importing it.
/* eslint-disable sw-deprecation-rules/private-feature-declarations -- see the comment above */
Shopwell.Component.register('sw-membership-purchase-list', () => import('./page/sw-membership-purchase-list'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations -- see the comment above */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- the module itself has no export to declare
Module.register('sw-membership', {
    type: 'core',
    name: 'membership',
    title: 'sw-membership.purchase.general.mainMenuItemGeneral',
    description: 'sw-membership.purchase.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    // the color of the orders family: the entry lives inside the orders menu and marks its active
    // state with the parent color
    color: 'var(--sw-color-module-purple-default)',
    icon: 'regular-clipboard',
    favicon: 'icon-module-orders.svg',
    entity: 'membership_purchase',

    routes: {
        purchases: {
            component: 'sw-membership-purchase-list',
            path: 'purchases',
            meta: {
                privilege: 'loyalty.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },
    },

    navigation: [
        {
            // The runtime derives navigation ids from the module name - `membership` becomes
            // `sw-membership-` - so the id is written in that form already. The core declaration in
            // `CoreAdminMenuProvider` has to name the same key, or the menu would render the entry
            // twice: once from the database row and once from this declaration.
            id: 'sw-membership-purchases',
            path: 'sw.membership.purchases',
            label: 'sw-membership.purchase.general.mainMenuItemGeneral',
            icon: 'regular-clipboard',
            parent: 'sw-order',
            privilege: 'loyalty.viewer',
        },
    ],
});
