import './service';
import { grantPermissionsFromSdk, isPermissionGrantedFromSdk } from './composables/permissions';

/**
 * @private
 */
Shopwell.Component.register('sw-settings-services-index', () => import('./page/sw-settings-services-index'));

/**
 * @private
 */
Shopwell.Component.register(
    'sw-settings-services-dashboard-banner',
    () => import('./component/sw-settings-services-dashboard-banner'),
);

/**
 * @private
 */
Shopwell.Component.register(
    'sw-settings-services-grant-permissions-modal',
    () => import('./component/sw-settings-services-grant-permissions-modal'),
);

/**
 * @sw-package framework
 * @private
 */
Shopwell.Module.register('sw-settings-services', {
    type: 'core',
    name: 'services',
    title: 'sw-settings-services.general.title',
    description: 'sw-settings-services.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-view-grid',
    favicon: 'icon-module-settings.svg',
    entity: 'store_settings',

    routes: {
        index: {
            component: 'sw-settings-services-index',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'system.plugin_maintain',
            },
        },
    },

    settingsItem: {
        group: 'system',
        to: 'sw.settings.services.index',
        icon: 'regular-view-grid',
        privilege: 'system.plugin_maintain',
    },
});

Shopwell.ExtensionAPI.handle('servicePermissionGrant', grantPermissionsFromSdk);
Shopwell.ExtensionAPI.handle('servicePermissionIsGranted', isPermissionGrantedFromSdk);

/**
 * @sw-package framework
 * @private
 */
export {};
