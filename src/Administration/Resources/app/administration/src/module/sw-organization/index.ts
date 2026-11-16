/**
 * @sw-package framework
 */
import './acl';

/** @private */
Shopwell.Component.register('sw-organization-list', () => import('./page/sw-organization-list'));
/** @private */
Shopwell.Component.register('sw-organization-detail', () => import('./page/sw-organization-detail'));
/** @private */
Shopwell.Component.register('sw-organization-unit-tree', () => import('./component/sw-organization-unit-tree'));
/** @private */
Shopwell.Component.register('sw-organization-unit-tree-item', () => import('./component/sw-organization-unit-tree-item'));

const { Module } = Shopwell;

/**
 * The organizations module: a merchant's legal structures. An organization is a flat entity, its
 * units are a tree below it. A company and a department are both plain rows of that tree - the
 * level a unit sits on carries the meaning (ADR-002), so the tree shows no type badge and the
 * detail form asks no type.
 */
/** @private */
Module.register('sw-organization', {
    type: 'core',
    name: 'organization',
    title: 'sw-organization.general.mainMenuItemGeneral',
    description: 'sw-organization.general.description',
    color: 'var(--sw-color-module-neutral-default)',
    icon: 'regular-officebuilding',
    favicon: 'icon-module-settings.svg',
    entity: 'organization',

    routes: {
        index: {
            component: 'sw-organization-detail',
            path: 'index',
            meta: {
                privilege: 'organization.viewer',
            },
        },
        create: {
            component: 'sw-organization-detail',
            path: 'create',
            props: { default: { createOrganization: true } },
            meta: {
                parentPath: 'sw.organization.index',
                privilege: 'organization.creator',
            },
        },
        detail: {
            component: 'sw-organization-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'sw.organization.index',
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
            id: 'sw-organization',
            path: 'sw.organization.index',
            privilege: 'organization.viewer',
        },
    ],
});
