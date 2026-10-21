/**
 * @sw-package after-sales
 *
 * The settings of the returns.
 *
 * Two screens, both behind the settings entry and not behind the returns menu: the rules are a policy
 * decision a shop takes once, and the reasons are the vocabulary it offers - neither is part of the
 * daily queue.
 *
 * The rules are a system config domain (`core.orderReturn`), which is why the page is only a
 * `sw-system-config` with a save button. The reasons are an entity, so that page is a grid.
 */
const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-settings-order-return-index', () => import('./page/sw-settings-order-return-index'));
Shopwell.Component.register(
    'sw-settings-order-return-reason-list',
    () => import('./page/sw-settings-order-return-reason-list'),
);
Shopwell.Component.register(
    'sw-settings-order-return-reason-modal',
    () => import('./component/sw-settings-order-return-reason-modal'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-order-return', {
    type: 'core',
    name: 'settings-order-return',
    title: 'sw-settings-order-return.general.mainMenuItemGeneral',
    description: 'sw-settings-order-return.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-orange-default)',
    icon: 'regular-undo',
    favicon: 'icon-module-settings.svg',
    entity: 'order_return_reason',

    routes: {
        index: {
            component: 'sw-settings-order-return-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'order_return.viewer',
            },
        },
        reason: {
            component: 'sw-settings-order-return-reason-list',
            path: 'reason',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'order_return.viewer',
            },
        },
    },

    settingsItem: {
        // `commerce` and not `system`: the two screens change how the shop treats a customer, not how
        // the installation runs
        group: 'commerce',
        to: 'sw.settings.order.return.index',
        icon: 'regular-undo',
        privilege: 'order_return.viewer',
    },
});

// A module file without an import of its own is a global script, and its `const { Module }` would
// collide with the one of every other module. An empty export makes it a module; it exports nothing.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export {};
