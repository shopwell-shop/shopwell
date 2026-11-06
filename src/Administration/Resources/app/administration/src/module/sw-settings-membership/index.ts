import './acl';
import './service';
import defaultSearchConfiguration from './default-search-configuration';

/**
 * @sw-package checkout
 */

const { Module } = Shopwell;

// The pages are registered lazily and never exported: the module is the only entry point, so a
// plugin must extend them through their twig blocks instead of importing them.
/* eslint-disable sw-deprecation-rules/private-feature-declarations -- see the comment above */
Shopwell.Component.register('sw-membership-tier-list', () => import('./page/sw-membership-tier-list'));
Shopwell.Component.register('sw-membership-tier-detail', () => import('./page/sw-membership-tier-detail'));
Shopwell.Component.extend(
    'sw-membership-tier-create',
    'sw-membership-tier-detail',
    () => import('./page/sw-membership-tier-create'),
);
Shopwell.Component.register('sw-membership-plan-list', () => import('./page/sw-membership-plan-list'));
Shopwell.Component.register('sw-membership-plan-detail', () => import('./page/sw-membership-plan-detail'));
Shopwell.Component.extend(
    'sw-membership-plan-create',
    'sw-membership-plan-detail',
    () => import('./page/sw-membership-plan-create'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations -- see the comment above */

// A module is a registration, not an import: `manifest` is the public face of it.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- see the comment above
Module.register('sw-settings-membership', {
    type: 'core',
    name: 'settings-membership',
    title: 'sw-membership.general.mainMenuItemGeneral',
    description: 'sw-membership.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-star',
    favicon: 'icon-module-settings.svg',
    entity: 'loyalty_tier',

    routes: {
        index: {
            component: 'sw-membership-tier-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'loyalty.viewer',
            },
        },
        detail: {
            component: 'sw-membership-tier-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.membership.index',
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
            component: 'sw-membership-tier-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.membership.index',
                privilege: 'loyalty.creator',
            },
        },
        plans: {
            component: 'sw-membership-plan-list',
            path: 'plans',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'loyalty.viewer',
            },
        },
        planDetail: {
            component: 'sw-membership-plan-detail',
            path: 'plans/detail/:id',
            meta: {
                parentPath: 'sw.settings.membership.plans',
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
            component: 'sw-membership-plan-create',
            path: 'plans/create',
            meta: {
                parentPath: 'sw.settings.membership.plans',
                privilege: 'loyalty.creator',
            },
        },
    },

    settingsItem: [
        {
            id: 'sw-settings-membership',
            group: 'customer',
            to: 'sw.settings.membership.index',
            icon: 'regular-star',
            privilege: 'loyalty.viewer',
            label: 'sw-membership.general.mainMenuItemGeneral',
        },
        {
            id: 'sw-settings-membership-plans',
            group: 'customer',
            to: 'sw.settings.membership.plans',
            icon: 'regular-shopping-bag',
            privilege: 'loyalty.viewer',
            label: 'sw-membership.plan.general.mainMenuItemGeneral',
        },
    ],

    defaultSearchConfiguration,
});
