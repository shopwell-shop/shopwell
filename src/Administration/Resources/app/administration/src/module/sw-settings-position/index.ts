/**
 * @sw-package framework
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-settings-position-list', () => import('./page/sw-settings-position-list'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopwell;

/**
 * The positions module. A position is a label entity - name, sort value, active switch - so it is
 * edited in a modal instead of a detail route (uiux 3.3): a page of its own would be empty except
 * for the language switch.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-position', {
    type: 'core',
    name: 'settings-position',
    title: 'sw-settings-position.general.mainMenuItemGeneral',
    description: 'sw-settings-position.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-briefcase',
    favicon: 'icon-module-settings.svg',
    entity: 'position',

    routes: {
        index: {
            component: 'sw-settings-position-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'position.viewer',
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'sw.settings.position.index',
        icon: 'regular-briefcase',
        privilege: 'position.viewer',
    },
});
