/**
 * @sw-package framework
 */
import './acl';

/** @private */
Shopwell.Component.register('sw-settings-dictionary-list', () => import('./page/sw-settings-dictionary-list'));
/** @private */
Shopwell.Component.register('sw-settings-dictionary-detail', () => import('./page/sw-settings-dictionary-detail'));

const { Module } = Shopwell;

/**
 * The data dictionary: generic `dictionary_type` + `dictionary_entry` sets a merchant maintains,
 * replacing the former hard-coded `salutation` entity. A type is managed from the list page, its
 * entries from the detail page; both are plain DAL resources, so the pages only differ in which
 * repository they read and which fields they edit.
 */
/** @private */
Module.register('sw-settings-dictionary', {
    type: 'core',
    name: 'settings-dictionary',
    title: 'sw-settings-dictionary.general.mainMenuItemGeneral',
    description: 'sw-settings-dictionary.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-layer-group',
    favicon: 'icon-module-settings.svg',
    entity: 'dictionary_type',

    routes: {
        index: {
            component: 'sw-settings-dictionary-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'dictionary.viewer',
            },
        },
        detail: {
            component: 'sw-settings-dictionary-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.dictionary.index',
                privilege: 'dictionary.viewer',
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { dictionaryTypeId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-settings-dictionary',
            label: 'sw-settings-dictionary.general.mainMenuItemGeneral',
            parent: 'sw-settings',
            path: 'sw.settings.dictionary.index',
            icon: 'regular-layer-group',
            position: 120,
            privilege: 'dictionary.viewer',
        },
    ],

    settingsItem: {
        group: 'general',
        to: 'sw.settings.dictionary.index',
        icon: 'regular-layer-group',
        privilege: 'dictionary.viewer',
    },
});
