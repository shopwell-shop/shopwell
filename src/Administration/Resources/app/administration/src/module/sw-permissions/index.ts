/** @sw-package fundamentals@framework */
import type { RouteLocation } from 'vue-router';
import './acl';

/** @private */
Shopwell.Component.register('sw-permissions-menu-list', () => import('./page/sw-permissions-menu-list'));
/** @private */
Shopwell.Component.register('sw-permissions-role-detail', () => import('./page/sw-permissions-role-detail'));
/** @private */
Shopwell.Component.register('sw-permissions-role-list', () => import('./page/sw-permissions-role-list'));
/** @private */
Shopwell.Component.register('sw-permissions-role-view-detailed', () => import('./view/sw-permissions-role-view-detailed'));
/** @private */
Shopwell.Component.register('sw-permissions-role-view-general', () => import('./view/sw-permissions-role-view-general'));
/** @private */
Shopwell.Component.register('sw-permissions-role-view-menus', () => import('./view/sw-permissions-role-view-menus'));
/** @private */
Shopwell.Component.register('sw-permissions-menu-detail', () => import('./component/sw-permissions-menu-detail'));
/** @private */
Shopwell.Component.register('sw-permissions-menu-tree', () => import('./component/sw-permissions-menu-tree'));
/** @private */
Shopwell.Component.register(
    'sw-permissions-role-mcp-reference-modal',
    () => import('./component/sw-permissions-role-mcp-reference-modal'),
);
/** @private */
Shopwell.Component.register('sw-permissions-additional', () => import('./components/sw-permissions-additional'));
/** @private */
Shopwell.Component.register(
    'sw-permissions-detailed-additional',
    () => import('./components/sw-permissions-detailed-additional'),
);
/** @private */
Shopwell.Component.register('sw-permissions-detailed-grid', () => import('./components/sw-permissions-detailed-grid'));
/** @private */
Shopwell.Component.register('sw-permissions-grid', () => import('./components/sw-permissions-grid'));

/** @private */
Shopwell.Module.register('sw-permissions', {
    type: 'core',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    favicon: 'icon-module-settings.svg',
    name: 'permissions',
    title: 'sw-permissions.general.label',
    description: 'sw-permissions.general.label',
    icon: 'regular-user-shield',
    entity: 'acl_role',
    routes: {
        role: { component: 'sw-permissions-role-list', path: 'role', meta: { privilege: 'permissions.viewer' } },
        'role.detail': {
            component: 'sw-permissions-role-detail',
            path: 'role/detail/:id?',
            meta: { parentPath: 'sw.permissions.role', privilege: 'permissions.viewer' },
            redirect: (route: RouteLocation) => ({
                name: route.params.id ? 'sw.permissions.role.detail.menus' : 'sw.permissions.role.detail.general',
            }),
            children: {
                general: {
                    component: 'sw-permissions-role-view-general',
                    path: 'general',
                    meta: { parentPath: 'sw.permissions.role', privilege: 'permissions.viewer' },
                },
                menus: {
                    component: 'sw-permissions-role-view-menus',
                    path: 'menus',
                    meta: { parentPath: 'sw.permissions.role', privilege: 'permissions.viewer' },
                },
                'detailed-privileges': {
                    component: 'sw-permissions-role-view-detailed',
                    path: 'detailed-privileges',
                    meta: { parentPath: 'sw.permissions.role', privilege: 'permissions.viewer' },
                },
            },
        },
        menu: { component: 'sw-permissions-menu-list', path: 'menu', meta: { privilege: 'admin_menu.viewer' } },
    },
    navigation: [
        {
            id: 'sw-permissions-role',
            parent: 'sw-system',
            label: 'sw-permissions.roles.general.label',
            path: 'sw.permissions.role',
            position: 20,
            privilege: 'permissions.viewer',
        },
        {
            id: 'sw-permissions-menu',
            parent: 'sw-system',
            label: 'sw-permissions.menu.headline',
            path: 'sw.permissions.menu',
            position: 30,
            privilege: 'admin_menu.viewer',
        },
    ],
});
