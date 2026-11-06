/**
 * @sw-package framework
 */
import './acl';

const { Module } = Shopwell;

/** @private */
Shopwell.Component.register('sw-payment-cashier-profiles', () => import('./component/sw-payment-cashier-profiles'));

/** @private */
Shopwell.Component.register('sw-settings-payment-gateway-list', () => import('./page/sw-settings-payment-gateway-list'));
/** @private */
Shopwell.Component.register('sw-settings-payment-gateway-detail', () => import('./page/sw-settings-payment-gateway-detail'));

/** @private */
Module.register('sw-settings-payment-gateway', {
    type: 'core',
    name: 'settings-payment-gateway',
    title: 'sw-settings-payment-gateway.general.mainMenuItemGeneral',
    description: 'sw-settings-payment-gateway.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-cyan-default)',
    icon: 'regular-plug',
    favicon: 'icon-module-settings.svg',
    entity: 'payment_app',

    routes: {
        list: {
            component: 'sw-settings-payment-gateway-list',
            path: 'list',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'payment_app.viewer',
            },
        },

        detail: {
            component: 'sw-settings-payment-gateway-detail',
            path: 'detail/:id/:section?',
            meta: {
                parentPath: 'sw.settings.payment.gateway.list',
                privilege: 'payment_app.viewer',
            },
            props: {
                default(route: { params: { id: string; section?: string } }) {
                    return { appId: route.params.id, section: route.params.section || 'collection' };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-settings-payment-gateway',
            label: 'sw-settings-payment-gateway.general.mainMenuItemGeneral',
            parent: 'sw-settings',
            path: 'sw.settings.payment.gateway.list',
            icon: 'regular-plug',
            position: 800,
            privilege: 'payment_app.viewer',
        },
    ],

    settingsItem: {
        group: 'payment',
        to: 'sw.settings.payment.gateway.list',
        icon: 'regular-plug',
        privilege: 'payment_app.viewer',
    },
});
