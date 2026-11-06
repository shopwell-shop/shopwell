/**
 * @sw-package framework
 */
import './acl';

const { Module } = Shopwell;

/** @private */
Shopwell.Component.register('sw-settings-payment-provider-list', () => import('./page/sw-settings-payment-provider-list'));
/** @private */
Shopwell.Component.register(
    'sw-settings-payment-provider-detail',
    () => import('./page/sw-settings-payment-provider-detail'),
);

/** @private */
Module.register('sw-settings-payment-provider', {
    type: 'core',
    name: 'settings-payment-provider',
    title: 'sw-settings-payment-provider.general.mainMenuItemGeneral',
    description: 'sw-settings-payment-provider.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-cyan-default)',
    icon: 'regular-credit-card',
    favicon: 'icon-module-settings.svg',
    entity: 'payment_account',

    routes: {
        index: {
            component: 'sw-settings-payment-provider-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'payment_account.viewer',
            },
        },

        create: {
            component: 'sw-settings-payment-provider-detail',
            path: 'create',
            meta: { parentPath: 'sw.settings.payment.provider.index', privilege: 'payment_account.creator' },
        },

        detail: {
            component: 'sw-settings-payment-provider-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.payment.provider.index',
                privilege: 'payment_account.viewer',
            },
            props: {
                default(route: { params: { id: string } }) {
                    return { accountId: route.params.id };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-settings-payment-provider',
            label: 'sw-settings-payment-provider.general.mainMenuItemGeneral',
            parent: 'sw-settings',
            path: 'sw.settings.payment.provider.index',
            icon: 'regular-credit-card',
            position: 810,
            privilege: 'payment_account.viewer',
        },
    ],

    settingsItem: {
        group: 'payment',
        to: 'sw.settings.payment.provider.index',
        icon: 'regular-credit-card',
        privilege: 'payment_account.viewer',
    },
});
