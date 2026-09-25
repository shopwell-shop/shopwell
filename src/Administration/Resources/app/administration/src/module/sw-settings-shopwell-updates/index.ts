/**
 * @sw-package framework
 */

import './acl';

const { Component, Module } = Shopwell;

/** @private */
Component.register(
    'sw-settings-shopwell-updates-extensions',
    () => import('./view/sw-settings-shopwell-updates-extensions'),
);
/** @private */
Component.register('sw-settings-shopwell-updates-wizard', () => import('./page/sw-settings-shopwell-updates-wizard'));

/**
 * @private
 */
Module.register('sw-settings-shopwell-updates', {
    type: 'core',
    name: 'settings-shopwell-updates',
    display: !Shopwell.Context.app.hideUpdateModule,
    title: 'sw-settings-shopwell-updates.general.menuTitle',
    description: 'sw-settings-shopwell-updates.general.menuTitle',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-sync',
    favicon: 'icon-module-settings.svg',

    routes: {
        wizard: {
            component: 'sw-settings-shopwell-updates-wizard',
            path: 'wizard',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.core_update',
            },
        },
    },

    settingsItem: {
        privilege: 'system.core_update',
        group: 'system',
        to: 'sw.settings.shopwell.updates.wizard',
        icon: 'regular-sync',
    },
});
