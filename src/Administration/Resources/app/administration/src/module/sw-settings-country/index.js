/**
 * @sw-package fundamentals@discovery
 */

import './acl';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-settings-country-list', () => import('./page/sw-settings-country-list'));
Shopwell.Component.register('sw-settings-country-detail', () => import('./page/sw-settings-country-detail'));
Shopwell.Component.extend(
    'sw-settings-country-create',
    'sw-settings-country-detail',
    () => import('./page/sw-settings-country-create'),
);
Shopwell.Component.register('sw-country-state-detail', () => import('./component/sw-country-state-detail'));
Shopwell.Component.register('sw-settings-country-general', () => import('./component/sw-settings-country-general'));
Shopwell.Component.register('sw-settings-country-state', () => import('./component/sw-settings-country-state'));
Shopwell.Component.register(
    'sw-settings-country-currency-dependent-modal',
    () => import('./component/sw-settings-country-currency-dependent-modal'),
);
Shopwell.Component.register(
    'sw-settings-country-currency-hamburger-menu',
    () => import('./component/sw-settings-country-currency-hamburger-menu'),
);
Shopwell.Component.register(
    'sw-settings-country-preview-template',
    () => import('./component/sw-settings-country-preview-template'),
);
Shopwell.Component.register(
    'sw-settings-country-new-snippet-modal',
    () => import('./component/sw-settings-country-new-snippet-modal'),
);
Shopwell.Component.register(
    'sw-settings-country-address-handling',
    () => import('./component/sw-settings-country-address-handling'),
);
Shopwell.Component.register('sw-multi-snippet-drag-and-drop', () => import('./component/sw-multi-snippet-drag-and-drop'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-country', {
    type: 'core',
    name: 'settings-country',
    title: 'sw-settings-country.general.mainMenuItemGeneral',
    description: 'Country section in the settings module',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-map',
    favicon: 'icon-module-settings.svg',
    entity: 'country',

    routes: {
        index: {
            component: 'sw-settings-country-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'country.viewer',
            },
        },
        detail: {
            component: 'sw-settings-country-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.country.index',
                privileges: ['country.viewer', 'country.editor'],
            },

            redirect: {
                name: 'sw.settings.country.detail.general',
            },

            children: {
                general: {
                    component: 'sw-settings-country-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: ['country.editor', 'country.creator'],
                    },
                },

                state: {
                    component: 'sw-settings-country-state',
                    path: 'state',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: ['country.editor', 'country.creator'],
                    },
                },

                'address-handling': {
                    component: 'sw-settings-country-address-handling',
                    path: 'address-handling',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: ['country.editor', 'country.creator'],
                    },
                },
            },
        },
        create: {
            component: 'sw-settings-country-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.country.index',
                privilege: 'country.creator',
            },

            redirect: {
                name: 'sw.settings.country.create.general',
            },

            children: {
                general: {
                    component: 'sw-settings-country-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privilege: 'country.creator',
                    },
                },

                state: {
                    component: 'sw-settings-country-state',
                    path: 'state',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privilege: 'country.creator',
                    },
                },

                'address-handling': {
                    component: 'sw-settings-country-address-handling',
                    path: 'address-handling',
                    meta: {
                        parentPath: 'sw.settings.country.index',
                        privileges: 'country.creator',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'localization',
        to: 'sw.settings.country.index',
        icon: 'regular-map',
        privilege: 'country.viewer',
    },
});
