/** @sw-package fundamentals@framework */
import './acl';

/** @private */
Shopwell.Component.extend('sw-user-create', 'sw-user-detail', () => import('./page/sw-user-create'));
/** @private */
Shopwell.Component.register('sw-user-detail', () => import('./page/sw-user-detail'));
/** @private */
Shopwell.Component.register('sw-user-list', () => import('./page/sw-user-list'));
/** @private */
Shopwell.Component.register('sw-user-sso-detail', () => import('./page/sw-user-sso-detail'));
/** @private */
Shopwell.Component.register(
    'sw-user-sso-access-key-create-modal',
    () => import('./components/sw-user-sso-access-key-create-modal'),
);
/** @private */
Shopwell.Component.register('sw-user-sso-invitation-modal', () => import('./components/sw-user-sso-invitation-modal'));
/** @private */
Shopwell.Component.register('sw-user-sso-status-label', () => import('./components/sw-user-sso-status-label'));

/** @private */
Shopwell.Module.register('sw-user', {
    type: 'core',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    favicon: 'icon-module-settings.svg',
    name: 'user',
    title: 'sw-user.general.label',
    description: 'sw-user.general.label',
    icon: 'regular-user',
    entity: 'user',
    routes: {
        index: { component: 'sw-user-list', path: 'index', meta: { privilege: 'user.viewer' } },
        detail: {
            component: 'sw-user-detail',
            path: 'detail/:id?',
            meta: { parentPath: 'sw.user.index', privilege: 'user.viewer' },
        },
        'sso.detail': {
            component: 'sw-user-sso-detail',
            path: 'sso/detail/:id?',
            meta: { parentPath: 'sw.user.index', privilege: 'user.viewer' },
        },
        create: {
            component: 'sw-user-create',
            path: 'create',
            meta: { parentPath: 'sw.user.index', privilege: 'user.creator' },
        },
    },
    navigation: [
        {
            id: 'sw-system',
            label: 'global.sw-admin-menu.navigation.mainMenuItemSystem',
            icon: 'regular-user-shield',
            position: 95,
        },
        {
            id: 'sw-user',
            parent: 'sw-system',
            label: 'sw-user.general.label',
            path: 'sw.user.index',
            position: 10,
            privilege: 'user.viewer',
        },
    ],
});
