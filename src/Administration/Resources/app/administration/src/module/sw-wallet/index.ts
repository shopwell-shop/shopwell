/**
 * @sw-package checkout
 */
import './acl';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-wallet-top-up-list', () => import('./page/sw-wallet-top-up-list'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-wallet', {
    type: 'core',
    name: 'settings-wallet',
    title: 'sw-wallet.general.mainMenuItemGeneral',
    description: 'sw-wallet.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-money-transfer',
    favicon: 'icon-module-settings.svg',
    entity: 'customer_wallet_top_up',

    routes: {
        topUps: {
            component: 'sw-wallet-top-up-list',
            path: 'top-ups',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'wallet.viewer',
            },
        },
    },

    settingsItem: [
        {
            id: 'sw-settings-wallet-top-ups',
            group: 'customer',
            to: 'sw.settings.wallet.topUps',
            icon: 'regular-money-transfer',
            privilege: 'wallet.viewer',
            label: 'sw-wallet.topUp.general.mainMenuItemGeneral',
        },
    ],
});
