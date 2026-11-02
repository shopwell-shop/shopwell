// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Component.register('sw-settings-user-permission', () => import('./page/sw-settings-user-permission'));

/**
 * @sw-package fundamentals@framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Module.register('sw-settings-user-permission', {
    type: 'core',
    name: 'settings-user-permission',
    title: 'sw-settings-user-permission.general.mainMenuItemGeneral',
    description: 'sw-settings-user-permission.general.description',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-lock',
    favicon: 'icon-module-settings.svg',

    routes: {
        index: {
            component: 'sw-settings-user-permission',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'users_and_permissions.viewer',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.user.permission.index',
        icon: 'regular-lock',
        privilege: 'users_and_permissions.viewer',
    },
});
