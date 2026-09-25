/**
 * @sw-package fundamentals@after-sales
 */
import ImportExportService from './service/importExport.service';
import ImportExportProfileMappingService from './service/importExportProfileMapping.service';
import ImportExportProfileUpdateByService from './service/importExportUpdateByMapping.service';
import './acl';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('importExport', () => {
    return new ImportExportService(Shopwell.Application.getContainer('init').httpClient, Shopwell.Service('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('importExportProfileMapping', () => {
    return new ImportExportProfileMappingService(Shopwell.EntityDefinition);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('importExportUpdateByMapping', () => {
    return new ImportExportProfileUpdateByService(Shopwell.EntityDefinition);
});

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-import-export', () => import('./page/sw-import-export'));
Shopwell.Component.register('sw-import-export-exporter', () => import('./component/sw-import-export-exporter'));
Shopwell.Component.register('sw-import-export-importer', () => import('./component/sw-import-export-importer'));
Shopwell.Component.register('sw-import-export-activity', () => import('./component/sw-import-export-activity'));
Shopwell.Component.register(
    'sw-import-export-activity-log-info-modal',
    () => import('./component/sw-import-export-activity-log-info-modal'),
);
Shopwell.Component.register(
    'sw-import-export-activity-result-modal',
    () => import('./component/sw-import-export-activity-result-modal'),
);
Shopwell.Component.register(
    'sw-import-export-edit-profile-modal',
    () => import('./component/sw-import-export-edit-profile-modal'),
);
Shopwell.Component.register(
    'sw-import-export-edit-profile-modal-mapping',
    () => import('./component/sw-import-export-edit-profile-modal-mapping'),
);
Shopwell.Component.register(
    'sw-import-export-edit-profile-modal-identifiers',
    () => import('./component/sw-import-export-edit-profile-modal-identifiers'),
);
Shopwell.Component.register(
    'sw-import-export-entity-path-select',
    () => import('./component/sw-import-export-entity-path-select'),
);
Shopwell.Component.register(
    'sw-import-export-edit-profile-field-indicators',
    () => import('./component/sw-import-export-edit-profile-field-indicators'),
);
Shopwell.Component.register(
    'sw-import-export-edit-profile-import-settings',
    () => import('./component/sw-import-export-edit-profile-import-settings'),
);
Shopwell.Component.register(
    'sw-import-export-edit-profile-general',
    () => import('./component/sw-import-export-edit-profile-general'),
);
Shopwell.Component.register(
    'sw-import-export-new-profile-wizard',
    () => import('./component/profile-wizard/sw-import-export-new-profile-wizard'),
);
Shopwell.Component.register(
    'sw-import-export-new-profile-wizard-general-page',
    () => import('./component/profile-wizard/sw-import-export-new-profile-wizard-general-page'),
);
Shopwell.Component.register(
    'sw-import-export-new-profile-wizard-csv-page',
    () => import('./component/profile-wizard/sw-import-export-new-profile-wizard-csv-page'),
);
Shopwell.Component.register(
    'sw-import-export-new-profile-wizard-mapping-page',
    () => import('./component/profile-wizard/sw-import-export-new-profile-wizard-mapping-page'),
);
Shopwell.Component.register('sw-import-export-view-import', () => import('./view/sw-import-export-view-import'));
Shopwell.Component.register('sw-import-export-view-export', () => import('./view/sw-import-export-view-export'));
Shopwell.Component.register('sw-import-export-view-profiles', () => import('./view/sw-import-export-view-profiles'));
Shopwell.Component.register('sw-import-export-progress', () => import('./component/sw-import-export-progress'));
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Module.register('sw-import-export', {
    type: 'core',
    name: 'ImportExport',
    title: 'sw-import-export.general.mainMenuItemGeneral',
    description: 'sw-import-export.general.descriptionTextModule',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-database',
    entity: 'import_export_profile',
    routePrefixPath: 'sw/import-export',

    routes: {
        index: {
            component: 'sw-import-export',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'system.import_export',
            },
            redirect: {
                name: 'sw.import.export.index.import',
            },

            children: {
                import: {
                    component: 'sw-import-export-view-import',
                    path: 'import',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.import_export',
                    },
                },
                export: {
                    component: 'sw-import-export-view-export',
                    path: 'export',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.import_export',
                    },
                },
                profiles: {
                    component: 'sw-import-export-view-profiles',
                    path: 'profiles',
                    meta: {
                        parentPath: 'sw.settings.index',
                        privilege: 'system.import_export',
                    },
                },
            },
        },
    },

    settingsItem: {
        group: 'automation',
        to: 'sw.import.export.index',
        icon: 'regular-database',
        privilege: 'system.import_export',
    },
});
