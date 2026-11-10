/**
 * @sw-package fundamentals@framework
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-system-user-list', () => import('./page/sw-system-user-list'));
Shopwell.Component.register('sw-system-role-list', () => import('./page/sw-system-role-list'));
Shopwell.Component.register('sw-system-menu-list', () => import('./page/sw-system-menu-list'));
Shopwell.Component.register('sw-system-menu-detail', () => import('./component/sw-system-menu-detail'));
Shopwell.Component.register('sw-system-menu-tree', () => import('./component/sw-system-menu-tree'));
Shopwell.Component.register(
    'sw-system-additional-permissions',
    () => import('./components/sw-system-additional-permissions'),
);
Shopwell.Component.register('sw-system-permissions-grid', () => import('./components/sw-system-permissions-grid'));
Shopwell.Component.register(
    'sw-system-detailed-permissions-grid',
    () => import('./components/sw-system-detailed-permissions-grid'),
);
Shopwell.Component.register(
    'sw-system-detailed-additional-permissions',
    () => import('./components/sw-system-detailed-additional-permissions'),
);
Shopwell.Component.register('sw-system-user-detail', () => import('./page/sw-system-user-detail'));
Shopwell.Component.extend('sw-system-user-create', 'sw-system-user-detail', () => import('./page/sw-system-user-create'));
Shopwell.Component.register('sw-system-role-detail', () => import('./page/sw-system-role-detail'));
Shopwell.Component.register('sw-system-role-view-general', () => import('./view/sw-system-role-view-general'));
Shopwell.Component.register('sw-system-role-view-detailed', () => import('./view/sw-system-role-view-detailed'));
Shopwell.Component.register('sw-system-sso-user-detail', () => import('./page/sw-system-sso-user-detail'));
Shopwell.Component.register(
    'sw-system-user-sso-invitation-modal',
    () => import('./components/sw-system-user-sso-invitation-modal'),
);
Shopwell.Component.register('sw-system-user-sso-status-label', () => import('./components/sw-system-user-sso-status-label'));
Shopwell.Component.register(
    'sw-system-user-sso-access-key-create-modal',
    () => import('./components/sw-system-user-sso-access-key-create-modal'),
);
Shopwell.Component.register(
    'sw-system-role-mcp-reference-modal',
    () => import('./component/sw-system-role-mcp-reference-modal'),
);

/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Module.register('sw-system', {
    type: 'core',
    name: 'system',
    title: 'sw-system.general.label',
    description: 'sw-system.general.label',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-user-shield',
    favicon: 'icon-module-settings.svg',
    entity: 'user',

    routes: {
        user: {
            component: 'sw-system-user-list',
            path: 'user',
            meta: {
                privilege: 'users_and_permissions.viewer',
            },
        },
        'user.detail': {
            component: 'sw-system-user-detail',
            path: 'user/detail/:id?',
            meta: {
                parentPath: 'sw.system.user',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'user.sso.detail': {
            component: 'sw-system-sso-user-detail',
            path: 'user/sso/detail/:id?',
            meta: {
                parentPath: 'sw.system.user',
                privilege: 'users_and_permissions.viewer',
            },
        },
        'user.create': {
            component: 'sw-system-user-create',
            path: 'user/create',
            meta: {
                parentPath: 'sw.system.user',
                privilege: 'users_and_permissions.creator',
            },
        },
        role: {
            component: 'sw-system-role-list',
            path: 'role',
            meta: {
                privilege: 'users_and_permissions.viewer',
            },
        },
        'role.detail': {
            component: 'sw-system-role-detail',
            path: 'role/detail/:id?',
            meta: {
                parentPath: 'sw.system.role',
                privilege: 'users_and_permissions.viewer',
            },
            redirect: {
                name: 'sw.system.role.detail.general',
            },
            children: {
                general: {
                    component: 'sw-system-role-view-general',
                    path: 'general',
                    meta: {
                        parentPath: 'sw.system.role',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
                'detailed-privileges': {
                    component: 'sw-system-role-view-detailed',
                    path: 'detailed-privileges',
                    meta: {
                        parentPath: 'sw.system.role',
                        privilege: 'users_and_permissions.viewer',
                    },
                },
            },
        },
        menu: {
            component: 'sw-system-menu-list',
            path: 'menu',
            meta: {
                privilege: 'admin_menu.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-system',
            label: 'sw-system.general.mainMenuItemSystem',
            color: 'var(--sw-color-module-neutral-default)',
            icon: 'regular-user-shield',
            position: 95,
        },
        {
            id: 'sw-system-user',
            parent: 'sw-system',
            label: 'sw-system.navigation.user',
            path: 'sw.system.user',
            position: 10,
            privilege: 'users_and_permissions.viewer',
        },
        {
            id: 'sw-system-role',
            parent: 'sw-system',
            label: 'sw-system.navigation.role',
            path: 'sw.system.role',
            position: 20,
            privilege: 'users_and_permissions.viewer',
        },
        {
            id: 'sw-system-menu',
            parent: 'sw-system',
            label: 'sw-system.navigation.menu',
            path: 'sw.system.menu',
            position: 30,
            privilege: 'admin_menu.viewer',
        },
    ],
});
