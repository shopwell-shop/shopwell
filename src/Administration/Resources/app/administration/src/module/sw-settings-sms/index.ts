/**
 * @sw-package framework
 */
import './acl';
import SmsGatewayProviderService from './service/sms-gateway-provider.service';

const { Module } = Shopwell;

/**
 * The navigation between the three screens of the module. It is a component of its own because each
 * list shows it, so the three stay reachable from one another.
 *
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-sms-section-tabs', () => import('./component/sw-settings-sms-section-tabs'));

/**
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-sms-gateway-list', () => import('./page/sw-settings-sms-gateway-list'));

/**
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-sms-gateway-detail', () => import('./page/sw-settings-sms-gateway-detail'));

/**
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-sms-template-list', () => import('./page/sw-settings-sms-template-list'));

/**
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-sms-template-detail', () => import('./page/sw-settings-sms-template-detail'));

/**
 * @sw-package framework
 * @private
 */
Shopwell.Component.register('sw-settings-sms-delivery-list', () => import('./page/sw-settings-sms-delivery-list'));

/**
 * The mapping of one template onto the account of a provider. It is a component of its own because
 * the template detail holds a grid of them, one row per provider account.
 *
 * @sw-package framework
 * @private
 */
Shopwell.Component.register(
    'sw-settings-sms-template-mapping-grid',
    () => import('./component/sw-settings-sms-template-mapping-grid'),
);

/**
 * @sw-package framework
 * @private
 */
Shopwell.Service().register('smsGatewayProviderService', () => {
    return new SmsGatewayProviderService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

/**
 * @private
 */
Module.register('sw-settings-sms', {
    type: 'core',
    name: 'settings-sms',
    title: 'sw-settings-sms.general.mainMenuItemGeneral',
    description: 'sw-settings-sms.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-envelope',
    favicon: 'icon-module-settings.svg',
    entity: 'sms_gateway',

    routes: {
        gateway: {
            component: 'sw-settings-sms-gateway-list',
            path: 'gateway',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'sms_gateway.viewer',
            },
        },
        // The three screens live in one module, so a screen that has more than a list says which
        // screen it belongs to - the key is part of the route name, and the name is what the
        // administration navigates by.
        'gateway.detail': {
            component: 'sw-settings-sms-gateway-detail',
            path: 'gateway/detail/:id',
            meta: {
                parentPath: 'sw.settings.sms.gateway',
                privilege: 'sms_gateway.viewer',
            },
            props: {
                default(route: { params: { id: string } }) {
                    return {
                        gatewayId: route.params.id,
                    };
                },
            },
        },
        'gateway.create': {
            component: 'sw-settings-sms-gateway-detail',
            path: 'gateway/create',
            meta: {
                parentPath: 'sw.settings.sms.gateway',
                privilege: 'sms_gateway.creator',
            },
        },
        template: {
            component: 'sw-settings-sms-template-list',
            path: 'template',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'sms_template.viewer',
            },
        },
        'template.detail': {
            component: 'sw-settings-sms-template-detail',
            path: 'template/detail/:id',
            meta: {
                parentPath: 'sw.settings.sms.template',
                privilege: 'sms_template.viewer',
            },
            props: {
                default(route: { params: { id: string } }) {
                    return {
                        templateId: route.params.id,
                    };
                },
            },
        },
        'template.create': {
            component: 'sw-settings-sms-template-detail',
            path: 'template/create',
            meta: {
                parentPath: 'sw.settings.sms.template',
                privilege: 'sms_template.creator',
            },
        },
        delivery: {
            component: 'sw-settings-sms-delivery-list',
            path: 'delivery',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'sms_delivery.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.sms.gateway',
        icon: 'regular-envelope',
        privilege: 'sms_gateway.viewer',
    },
});
