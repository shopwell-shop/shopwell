import './acl';
import defaultSearchConfiguration from './default-search-configuration';
import LoyaltyApiService from './service/loyalty.api.service';

/**
 * @sw-package checkout
 */

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-loyalty-tier-list', () => import('./page/sw-loyalty-tier-list'));
Shopwell.Component.register('sw-loyalty-tier-detail', () => import('./page/sw-loyalty-tier-detail'));
Shopwell.Component.extend('sw-loyalty-tier-create', 'sw-loyalty-tier-detail', () => import('./page/sw-loyalty-tier-create'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

/**
 * @private
 */
Shopwell.Service().register('loyaltyApiService', () => {
    return new LoyaltyApiService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service().get('loginService'),
    );
});

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
    },

    settingsItem: {
        group: 'customer',
        to: 'sw.settings.loyalty.index',
        icon: 'regular-star',
        privilege: 'loyalty.viewer',
    },

    defaultSearchConfiguration,
});
