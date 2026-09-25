import './acl';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @sw-package checkout
 */

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-customer-list', () => import('./page/sw-customer-list'));
Shopwell.Component.register('sw-customer-detail', () => import('./page/sw-customer-detail'));
Shopwell.Component.register('sw-customer-create', () => import('./page/sw-customer-create'));
Shopwell.Component.register('sw-customer-detail-base', () => import('./view/sw-customer-detail-base'));
Shopwell.Component.register('sw-customer-detail-addresses', () => import('./view/sw-customer-detail-addresses'));
Shopwell.Component.register('sw-customer-detail-order', () => import('./view/sw-customer-detail-order'));
Shopwell.Component.register('sw-customer-base-form', () => import('./component/sw-customer-base-form'));
Shopwell.Component.register('sw-customer-base-info', () => import('./component/sw-customer-base-info'));
Shopwell.Component.register('sw-customer-address-form', () => import('./component/sw-customer-address-form'));
Shopwell.Component.register(
    'sw-customer-address-form-options',
    () => import('./component/sw-customer-address-form-options'),
);
Shopwell.Component.register('sw-customer-default-addresses', () => import('./component/sw-customer-default-addresses'));
Shopwell.Component.register(
    'sw-customer-imitate-customer-modal',
    () => import('./component/sw-customer-imitate-customer-modal'),
);
Shopwell.Component.register('sw-customer-card', () => import('./component/sw-customer-card'));
Shopwell.Component.register('sw-customer-convert-guest-modal', () => import('./component/sw-customer-convert-guest-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-customer', {
    type: 'core',
    name: 'customers',
    title: 'sw-customer.general.mainMenuItemGeneral',
    description: 'sw-customer.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-orange-default)',
    icon: 'regular-users',
    favicon: 'icon-module-customers.svg',
    entity: 'customer',

    routes: {
        index: {
            components: {
                default: 'sw-customer-list',
            },
            path: 'index',
            meta: {
                privilege: 'customer.viewer',
                appSystem: {
                    view: 'list',
                },
            },
        },

        create: {
            component: 'sw-customer-create',
            path: 'create',
            meta: {
                parentPath: 'sw.customer.index',
                privilege: 'customer.creator',
            },
        },

        detail: {
            component: 'sw-customer-detail',
            path: 'detail/:id',
            redirect: {
                name: 'sw.customer.detail.base',
            },
            children: {
                base: {
                    component: 'sw-customer-detail-base',
                    path: 'base',
                    meta: {
                        parentPath: 'sw.customer.index',
                        privilege: 'customer.viewer',
                    },
                },
                addresses: {
                    component: 'sw-customer-detail-addresses',
                    path: 'addresses',
                    meta: {
                        parentPath: 'sw.customer.index',
                        privilege: 'customer.viewer',
                    },
                },
                order: {
                    component: 'sw-customer-detail-order',
                    path: 'order',
                    meta: {
                        parentPath: 'sw.customer.index',
                        privilege: 'customer.viewer',
                    },
                },
            },
            meta: {
                privilege: 'customer.viewer',
                appSystem: {
                    view: 'detail',
                },
            },

            props: {
                default(route) {
                    return {
                        customerId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-customer',
            label: 'sw-customer.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-users',
            position: 40,
            privilege: 'customer.viewer',
        },
        {
            path: 'sw.customer.index',
            label: 'sw-customer.general.mainMenuItemList',
            color: 'var(--sw-color-module-orange-default)',
            icon: 'regular-users',
            parent: 'sw-customer',
            privilege: 'customer.viewer',
        },
    ],

    defaultSearchConfiguration,
});
