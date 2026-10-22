import './acl';
import './service';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @sw-package checkout
 */

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-loyalty-tier-list', () => import('./page/sw-loyalty-tier-list'));
Shopwell.Component.register('sw-loyalty-tier-detail', () => import('./page/sw-loyalty-tier-detail'));
Shopwell.Component.extend('sw-loyalty-tier-create', 'sw-loyalty-tier-detail', () => import('./page/sw-loyalty-tier-create'));
Shopwell.Component.register('sw-loyalty-plan-list', () => import('./page/sw-loyalty-plan-list'));
Shopwell.Component.register('sw-loyalty-plan-detail', () => import('./page/sw-loyalty-plan-detail'));
Shopwell.Component.extend('sw-loyalty-plan-create', 'sw-loyalty-plan-detail', () => import('./page/sw-loyalty-plan-create'));
Shopwell.Component.register('sw-loyalty-order-list', () => import('./page/sw-loyalty-order-list'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-loyalty', {
    type: 'core',
    name: 'settings-loyalty',
    title: 'sw-loyalty.general.mainMenuItemGeneral',
    description: 'sw-loyalty.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-star',
    favicon: 'icon-module-settings.svg',
    entity: 'loyalty_tier',

    routes: {
        index: {
            component: 'sw-loyalty-tier-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'loyalty.viewer',
            },
        },
        detail: {
            component: 'sw-loyalty-tier-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.loyalty.index',
                privilege: 'loyalty.viewer',
            },
            props: {
                default(route: { params: { id: string } }) {
                    return {
                        loyaltyTierId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
        create: {
            component: 'sw-loyalty-tier-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.loyalty.index',
                privilege: 'loyalty.creator',
            },
        },
        plans: {
            component: 'sw-loyalty-plan-list',
            path: 'plans',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'loyalty.viewer',
            },
        },
        planDetail: {
            component: 'sw-loyalty-plan-detail',
            path: 'plans/detail/:id',
            meta: {
                parentPath: 'sw.settings.loyalty.plans',
                privilege: 'loyalty.viewer',
            },
            props: {
                default(route: { params: { id: string } }) {
                    return {
                        planId: route.params.id.toLowerCase(),
                    };
                },
            },
        },
        planCreate: {
            component: 'sw-loyalty-plan-create',
            path: 'plans/create',
            meta: {
                parentPath: 'sw.settings.loyalty.plans',
                privilege: 'loyalty.creator',
            },
        },
        orders: {
            component: 'sw-loyalty-order-list',
            path: 'orders',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'loyalty.viewer',
            },
        },
    },

    settingsItem: [
        {
            id: 'sw-settings-loyalty',
            group: 'customer',
            to: 'sw.settings.loyalty.index',
            icon: 'regular-star',
            privilege: 'loyalty.viewer',
            label: 'sw-loyalty.general.mainMenuItemGeneral',
        },
        {
            id: 'sw-settings-loyalty-plans',
            group: 'customer',
            to: 'sw.settings.loyalty.plans',
            icon: 'regular-shopping-bag',
            privilege: 'loyalty.viewer',
            label: 'sw-loyalty.plan.general.mainMenuItemGeneral',
        },
        {
            id: 'sw-settings-loyalty-orders',
            group: 'customer',
            to: 'sw.settings.loyalty.orders',
            icon: 'regular-clipboard',
            privilege: 'loyalty.viewer',
            label: 'sw-loyalty.order.general.mainMenuItemGeneral',
        },
    ],

    defaultSearchConfiguration,
});
