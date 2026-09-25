/**
 * @sw-package framework
 */
import './acl';

const { Module } = Shopwell;

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.extend(
    'sw-settings-custom-field-set-create',
    'sw-settings-custom-field-set-detail',
    () => import('./page/sw-settings-custom-field-set-create'),
);
Shopwell.Component.register('sw-settings-custom-field-set-list', () => import('./page/sw-settings-custom-field-set-list'));
Shopwell.Component.register(
    'sw-settings-custom-field-set-detail',
    () => import('./page/sw-settings-custom-field-set-detail'),
);
Shopwell.Component.register(
    'sw-custom-field-translated-labels',
    () => import('./component/sw-custom-field-translated-labels'),
);
Shopwell.Component.register('sw-custom-field-set-detail-base', () => import('./component/sw-custom-field-set-detail-base'));
Shopwell.Component.register('sw-custom-field-list', () => import('./component/sw-custom-field-list'));
Shopwell.Component.register('sw-custom-field-detail', () => import('./component/sw-custom-field-detail'));
Shopwell.Component.register('sw-custom-field-type-base', () => import('./component/sw-custom-field-type-base'));
Shopwell.Component.extend(
    'sw-custom-field-type-select',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-select'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-entity',
    'sw-custom-field-type-select',
    () => import('./component/sw-custom-field-type-entity'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-text',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-text'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-number',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-number'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-date',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-date'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-checkbox',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-checkbox'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-text-editor',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-text-editor'),
);
Shopwell.Component.extend(
    'sw-custom-field-type-colorpicker',
    'sw-custom-field-type-base',
    () => import('./component/sw-custom-field-type-colorpicker'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-custom-field', {
    type: 'core',
    name: 'settings-custom-field',
    title: 'sw-settings-custom-field.general.mainMenuItemGeneral',
    description: 'sw-settings-custom-field.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-bars-square',
    favicon: 'icon-module-settings.svg',
    entity: 'custom-field-set',

    routes: {
        index: {
            component: 'sw-settings-custom-field-set-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index.system',
                privilege: 'custom_field.viewer',
            },
        },
        detail: {
            component: 'sw-settings-custom-field-set-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.custom.field.index',
                privilege: 'custom_field.viewer',
            },
        },
        create: {
            component: 'sw-settings-custom-field-set-create',
            path: 'create',
            meta: {
                parentPath: 'sw.settings.custom.field.index',
                privilege: 'custom_field.creator',
            },
        },
    },

    settingsItem: {
        group: 'content',
        to: 'sw.settings.custom.field.index',
        icon: 'regular-bars-square',
        privilege: 'custom_field.viewer',
    },
});
