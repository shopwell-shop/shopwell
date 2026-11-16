/**
 * @sw-package framework
 */
import './acl';

/** @private */
Shopwell.Component.register('sw-position-list', () => import('./page/sw-position-list'));

const { Module } = Shopwell;

/**
 * The positions module. A position is a label entity - name, sort value, active switch - so it is
 * edited in a modal instead of a detail route (uiux 3.3): a page of its own would be empty except
 * for the language switch.
 */
/** @private */
Module.register('sw-position', {
    type: 'core',
    name: 'position',
    title: 'sw-position.general.mainMenuItemGeneral',
    description: 'sw-position.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-briefcase',
    favicon: 'icon-module-settings.svg',
    entity: 'position',

    routes: {
        index: {
            component: 'sw-position-list',
            path: 'index',
            meta: {
                privilege: 'position.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-position',
            path: 'sw.position.index',
            privilege: 'position.viewer',
        },
    ],
});
