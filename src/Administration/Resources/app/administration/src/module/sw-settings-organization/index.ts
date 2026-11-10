/**
 * @sw-package framework
 */
import './acl';

/** @private */
Shopwell.Component.register('sw-settings-organization-list', () => import('./page/sw-settings-organization-list'));
/** @private */
Shopwell.Component.register('sw-settings-organization-detail', () => import('./page/sw-settings-organization-detail'));
/** @private */
Shopwell.Component.register(
    'sw-settings-organization-unit-tree',
    () => import('./component/sw-settings-organization-unit-tree'),
);
/** @private */
Shopwell.Component.register(
    'sw-settings-organization-unit-tree-item',
    () => import('./component/sw-settings-organization-unit-tree-item'),
);

const { Module } = Shopwell;

/**
 * The organizations module: a merchant's legal structures. An organization is a flat entity, its
 * units are a tree below it. A company and a department are both plain rows of that tree - the
 * level a unit sits on carries the meaning (ADR-002), so the tree shows no type badge and the
 * detail form asks no type.
 */
/** @private */
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
            component: 'sw-settings-organization-detail',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index',
                privilege: 'organization.viewer',
            },
        },
        create: {
            component: 'sw-settings-organization-detail',
            path: 'create',
            props: { default: { createOrganization: true } },
            meta: {
                parentPath: 'sw.settings.organization.index',
                privilege: 'organization.creator',
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
                default(route: { params: Record<string, string>; query: Record<string, unknown> }) {
                    return {
                        organizationId: route.params.id.toLowerCase(),
                        unitId: typeof route.query.unitId === 'string' ? route.query.unitId : null,
                        parentId: typeof route.query.parentId === 'string' ? route.query.parentId : null,
                    };
                },
            },
        },
    },

    navigation: [
        {
            id: 'sw-settings-organization',
            label: 'sw-settings-organization.general.mainMenuItemGeneral',
            parent: 'sw-settings',
            path: 'sw.settings.organization.index',
            icon: 'regular-officebuilding',
            position: 160,
            privilege: 'organization.viewer',
        },
    ],

    settingsItem: {
        group: 'general',
        to: 'sw.settings.organization.index',
        icon: 'regular-officebuilding',
        privilege: 'organization.viewer',
    },
});
