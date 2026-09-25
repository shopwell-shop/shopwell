/**
 * @sw-package discovery
 */
import './mixin/media-grid-listener.mixin';
import './mixin/media-sidebar-modal.mixin';
import './mixin/video-cover.mixin';
import './acl';
import defaultSearchConfiguration from './default-search-configuration';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-media-index', () => import('./page/sw-media-index'));
Shopwell.Component.register('sw-media-grid', () => import('./component/sw-media-grid'));
Shopwell.Component.register('sw-media-sidebar', () => import('./component/sidebar/sw-media-sidebar'));
Shopwell.Component.register(
    'sw-media-quickinfo-metadata-item',
    () => import('./component/sidebar/sw-media-quickinfo-metadata-item'),
);
Shopwell.Component.register('sw-media-quickinfo-usage', () => import('./component/sidebar/sw-media-quickinfo-usage'));
Shopwell.Component.extend('sw-media-collapse', 'sw-collapse', () => import('./component/sw-media-collapse'));
Shopwell.Component.register('sw-media-folder-info', () => import('./component/sidebar/sw-media-folder-info'));
Shopwell.Component.register('sw-media-quickinfo', () => import('./component/sidebar/sw-media-quickinfo'));
Shopwell.Component.register('sw-media-quickinfo-multiple', () => import('./component/sidebar/sw-media-quickinfo-multiple'));
Shopwell.Component.register('sw-media-tag', () => import('./component/sidebar/sw-media-tag'));
Shopwell.Component.register('sw-media-display-options', () => import('./component/sw-media-display-options'));
Shopwell.Component.register('sw-media-breadcrumbs', () => import('./component/sw-media-breadcrumbs'));
Shopwell.Component.register('sw-media-library', () => import('./component/sw-media-library'));
Shopwell.Component.register('sw-media-modal-v2', () => import('./component/sw-media-modal-v2'));

Shopwell.Component.register('sw-media-save-modal', () => import('./component/sw-media-save-modal'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-media', {
    type: 'core',
    name: 'media',
    title: 'sw-media.general.mainMenuItemGeneral',
    description: 'sw-media.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-pink-default)',
    icon: 'regular-image',
    favicon: 'icon-module-content.svg',
    entity: 'media',

    routes: {
        index: {
            components: {
                default: 'sw-media-index',
            },
            path: 'index/:folderId?',
            props: {
                default: (route) => {
                    return {
                        routeFolderId: route.params.folderId || null,
                    };
                },
            },
            meta: {
                privilege: 'media.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-media',
            label: 'sw-media.general.mainMenuItemGeneral',
            color: 'var(--sw-color-module-pink-default)',
            icon: 'regular-image',
            path: 'sw.media.index',
            position: 20,
            parent: 'sw-content',
            privilege: 'media.viewer',
        },
    ],

    defaultSearchConfiguration,
});
