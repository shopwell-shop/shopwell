/**
 * @sw-package framework
 */
import './acl';

/* eslint-disable sw-deprecation-rules/private-feature-declarations */
Shopwell.Component.register('sw-settings-organization-list', () => import('./page/sw-settings-organization-list'));
Shopwell.Component.register('sw-settings-organization-detail', () => import('./page/sw-settings-organization-detail'));
Shopwell.Component.register('sw-settings-organization-unit-tree', () => import('./component/sw-settings-organization-unit-tree'));
Shopwell.Component.register(
    'sw-settings-organization-unit-tree-item',
    () => import('./component/sw-settings-organization-unit-tree-item'),
);
/* eslint-enable sw-deprecation-rules/private-feature-declarations */

const { Module } = Shopwell;

/**
 * The organizations module: a merchant's legal structures. An organization is a flat entity, its
 * units are a tree below it. A company and a department are both plain rows of that tree - the
 * level a unit sits on carries the meaning (ADR-002), so the tree shows no type badge and the
 * detail form asks no type.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Module.register('sw-settings-organization', {
    type: 'core',
    name: 'settings-organization',
    title: 'sw-settings-organization.general.mainMenuItemGeneral',
    description: 'sw-settings-organization.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-officebuilding',
    favicon: 'icon-module-settings.svg',
    entity: 'organization',

    routes: {
        index: {
            component: 'sw-settings-organization-list',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'organization.viewer',
            },
        },
        detail: {
            component: 'sw-settings-organization-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.settings.organization.index',
                privilege: 'organization.viewer',
            },
            props: {
                default(route: { params: Record<string, string> }) {
                    return { organizationId: route.params.id.toLowerCase() };
                },
            },
        },
    },

    settingsItem: {
        group: 'general',
        to: 'sw.settings.organization.index',
        icon: 'regular-officebuilding',
        privilege: 'organization.viewer',
    },
});
